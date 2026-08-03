<?php

declare(strict_types=1);

use App\ServiceOrder\DTO\ServiceOrderFormData;

require __DIR__ . '/action-common.php';

admin_post();
$authorization->requirePermission('so_aquisicao.importar');
$companyId = max(0, (int) ($_POST['empresa_id'] ?? 0));
$target = 'adm/empresa-aquisicoes.php?empresa_id=' . $companyId;

try {
    $acquisitionId = filter_var($_POST['aquisicao_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $clientId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($companyId <= 0 || !is_int($acquisitionId) || !is_int($clientId)) throw new InvalidArgumentException('Revise os dados da conversão.');
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
    if ($application->soAcquisitionIntegrations()->findByExternalAcquisition($acquisitionId) !== null) throw new InvalidArgumentException('Esta aquisição já possui uma OS vinculada.');
    $acquisition = $detail['acquisition']; $items = [];
    foreach ($detail['items'] as $item) $items[] = ['type' => 'outro', 'origin' => 'manual', 'description' => (string) $item['produto'], 'unit' => 'un', 'quantity' => (string) $item['quantidade'], 'unit_price' => (string) $item['valor_unitario'], 'discount' => '0'];
    if ($items === []) throw new InvalidArgumentException('A aquisição não possui itens para criar uma OS.');
    $context = sprintf('Importada do SO. Aquisição: %s. Ofício: %s. Secretaria: %s. Entrega: %s.', $acquisition['numero_aq'] ?: '—', $acquisition['oficio_numero'] ?: '—', $acquisition['secretaria_nome'] ?: '—', $acquisition['codigo_entrega'] ?: '—');
    $form = ServiceOrderFormData::fromArray(['client_id' => $clientId, 'status' => 'aguardando_agendamento', 'priority' => (string) ($_POST['prioridade'] ?? 'media'), 'reported_problem' => $context, 'internal_notes' => trim((string) ($_POST['observacao'] ?? '')), 'items' => $items]);
    $connection = $application->database()->connection(); $connection->beginTransaction();
    try {
        $order = $application->serviceOrderManagement()->createOrder($form, null, null);
        $snapshot = json_encode(['acquisition' => $acquisition, 'items' => $detail['items']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $application->soAcquisitionIntegrations()->registerImportedAcquisition($order->id(), (int) $detail['supplier']['id'], $acquisitionId, (string) $acquisition['numero_aq'], $acquisition['codigo_entrega'] ?: null, (string) $acquisition['status'], $currentUser->id(), hash('sha256', 'so:aquisicao:' . $acquisitionId), hash('sha256', $snapshot), $snapshot);
        $audit = $connection->prepare("INSERT INTO empresa_auditoria_operacional (empresa_id, usuario_id, acesso_administrativo_id, acao, entidade_tipo, entidade_id, sessao_chave, ip, detalhes, criado_em) VALUES (:empresa, :usuario, :acesso, 'so_aquisicao_convertida_em_os', 'aquisicao_so', :entidade, :sessao, :ip, :detalhes, CURRENT_TIMESTAMP)");
        $audit->execute(['empresa' => $companyId, 'usuario' => $currentUser->id(), 'acesso' => $application->companyScope()->supportAccessId(), 'entidade' => $acquisitionId, 'sessao' => $currentUser->sessionBindingHash(), 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'detalhes' => json_encode(['ordem_servico_id' => $order->id(), 'aquisicao_so_id' => $acquisitionId], JSON_THROW_ON_ERROR)]);
        $connection->commit();
    } catch (Throwable $exception) { if ($connection->inTransaction()) $connection->rollBack(); throw $exception; }
    $session->flash('success', 'Ordem de serviço criada e vinculada à aquisição.');
    admin_action_redirect($target);
} catch (Throwable $exception) { admin_action_error($exception, $target); }
