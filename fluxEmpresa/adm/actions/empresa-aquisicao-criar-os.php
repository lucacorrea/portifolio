<?php

declare(strict_types=1);

use App\CRM\DTO\ClientFormData;
use App\ServiceOrder\DTO\ServiceOrderFormData;

require __DIR__ . '/action-common.php';

admin_post();
$authorization->requirePermission('so_aquisicao.importar');
$companyId = max(0, (int) ($_POST['empresa_id'] ?? 0));
$target = acquisition_return_target($companyId);

try {
    $acquisitionId = filter_var($_POST['aquisicao_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $clientId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($companyId <= 0 || !is_int($acquisitionId)) throw new InvalidArgumentException('Revise os dados da conversão.');
    $detail = $application->soAcquisitionBrowser()->details($companyId, $acquisitionId);
    if ($detail === null) throw new InvalidArgumentException('Aquisição não encontrada para o fornecedor vinculado.');
    $activeCompany = $application->activeCompanyContext()->current();
    if ($activeCompany === null || $activeCompany->id !== $companyId) {
        $session->regenerateId();
        $application->adminAccesses()->enter(
            $detail['company'],
            $currentUser->id(),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'Importação administrativa de aquisição do SO',
            $currentUser->sessionBindingHash(),
            $application->activeCompanyContext()
        );
    }
    if ($application->companyScope()->id() !== $companyId) throw new InvalidArgumentException('Não foi possível ativar o contexto da empresa.');
    if (!is_int($clientId)) {
        $prefeitura = null;
        foreach ($application->clientManagement()->listClients(['status' => 'ativo', 'limit' => 200]) as $client) {
            if (mb_strtolower(trim($client->name())) === 'prefeitura') { $prefeitura = $client; break; }
        }
        $clientId = $prefeitura?->id() ?? $application->clientManagement()->createClient(ClientFormData::fromArray([
            'person_type' => 'juridica',
            'name' => 'Prefeitura',
            'notes' => 'Cliente base criado automaticamente na importação de aquisição do SO.',
            'status' => 'ativo',
        ]))->id();
    }
    if ($application->soAcquisitionIntegrations()->findByExternalAcquisition($acquisitionId) !== null) throw new InvalidArgumentException('Esta aquisição já possui uma OS vinculada.');
    $acquisition = $detail['acquisition']; $items = [];
    foreach ($detail['items'] as $item) $items[] = ['type' => 'outro', 'origin' => 'manual', 'description' => (string) $item['produto'], 'unit' => 'un', 'quantity' => (string) $item['quantidade'], 'unit_price' => (string) $item['valor_unitario'], 'discount' => '0'];
    if ($items === []) throw new InvalidArgumentException('A aquisição não possui itens para criar uma OS.');
    $secretaria = trim((string) ($_POST['secretaria'] ?? $acquisition['secretaria_nome'] ?? ''));
    $local = trim((string) ($_POST['local'] ?? $acquisition['oficio_local'] ?? ''));
    $context = sprintf('Importada do SO. Aquisição: %s. Ofício: %s. Secretaria: %s. Local: %s. Entrega: %s.', $acquisition['numero_aq'] ?: '—', $acquisition['oficio_numero'] ?: '—', $secretaria ?: '—', $local ?: '—', $acquisition['codigo_entrega'] ?: '—');
    $form = ServiceOrderFormData::fromArray(['client_id' => $clientId, 'status' => 'aguardando_agendamento', 'priority' => (string) ($_POST['prioridade'] ?? 'media'), 'equipment_environment' => $secretaria, 'equipment_location' => $local, 'reported_problem' => $context, 'internal_notes' => trim((string) ($_POST['observacao'] ?? '')), 'items' => $items]);
    $connection = $application->database()->connection(); $connection->beginTransaction();
    try {
        $order = $application->serviceOrderManagement()->createOrder($form, null, null);
        $executionItems = [];
        foreach ($application->serviceOrderManagement()->getOrderItems($order->id()) as $orderItem) {
            $executionItems[] = [
                'type' => $orderItem->type(),
                'ordem_servico_item_id' => $orderItem->id(),
                'referencia_id' => $orderItem->referenceId(),
                'description' => $orderItem->description(),
                'unit' => $orderItem->unit(),
                'quantity' => $orderItem->quantity(),
                'unit_price' => $orderItem->unitPrice(),
                'discount' => $orderItem->discount(),
            ];
        }
        $application->serviceOrderFinalization()->finalizeImportedAcquisition(
            $order->id(),
            [
                'execution_items' => $executionItems,
                'saldo_observacao' => 'Cobrança pendente da Prefeitura referente à aquisição ' . ($acquisition['numero_aq'] ?: 'importada do SO') . '.',
            ],
            $currentUser->id()
        );
        $snapshot = json_encode(['acquisition' => $acquisition, 'items' => $detail['items']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $application->soAcquisitionIntegrations()->registerImportedAcquisition($order->id(), (int) $detail['supplier']['id'], $acquisitionId, (string) $acquisition['numero_aq'], $acquisition['codigo_entrega'] ?: null, (string) $acquisition['status'], $currentUser->id(), hash('sha256', 'so:aquisicao:' . $acquisitionId), hash('sha256', $snapshot), $snapshot);
        $audit = $connection->prepare("INSERT INTO empresa_auditoria_operacional (empresa_id, usuario_id, acesso_administrativo_id, acao, entidade_tipo, entidade_id, sessao_chave, ip, detalhes, criado_em) VALUES (:empresa, :usuario, :acesso, 'so_aquisicao_convertida_em_os', 'aquisicao_so', :entidade, :sessao, :ip, :detalhes, CURRENT_TIMESTAMP)");
        $audit->execute(['empresa' => $companyId, 'usuario' => $currentUser->id(), 'acesso' => $application->companyScope()->supportAccessId(), 'entidade' => $acquisitionId, 'sessao' => $currentUser->sessionBindingHash(), 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'detalhes' => json_encode(['ordem_servico_id' => $order->id(), 'aquisicao_so_id' => $acquisitionId], JSON_THROW_ON_ERROR)]);
        $connection->commit();
    } catch (Throwable $exception) { if ($connection->inTransaction()) $connection->rollBack(); throw $exception; }
    $session->flash('success', 'OS finalizada, vinculada à aquisição e enviada para cobrança pendente da Prefeitura.');
    admin_action_redirect($target);
} catch (Throwable $exception) { admin_action_error($exception, $target); }

function acquisition_return_target(int $companyId): string
{
    global $application;

    $fallback = 'adm/empresa-aquisicoes.php?empresa_id=' . $companyId;
    $referer = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if (!is_array($referer)) return $fallback;
    $path = ltrim((string) ($referer['path'] ?? ''), '/');
    $path = preg_replace('#^fluxEmpresa/#', '', $path) ?? '';
    if ($path !== 'adm/empresa-aquisicoes.php') return $fallback;

    parse_str((string) ($referer['query'] ?? ''), $query);
    if ((int) ($query['empresa_id'] ?? 0) !== $companyId) return $fallback;

    $target = $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $application->redirect()->sanitize($target) === $target ? $target : $fallback;
}
