<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('os.finalizar');

try {
    $orderId = os_posted_positive_int('id');
    $connection = $application->database()->connection();
    $integration = $connection->prepare(
        "SELECT 1
           FROM integracao_so_aquisicoes
          WHERE empresa_id = :company_id
            AND ordem_servico_id = :order_id
            AND direcao = 'so_para_flux'
            AND origem = 'aquisicao_so'
          LIMIT 1"
    );
    $integration->execute([
        'company_id' => $application->companyScope()->id(),
        'order_id' => $orderId,
    ]);
    if ($integration->fetchColumn() === false) {
        throw new InvalidArgumentException('Esta OS não é uma importação de aquisição do SO.');
    }

    $items = $application->serviceOrderManagement()->getOrderItems($orderId);
    if ($items === []) {
        throw new InvalidArgumentException('A OS importada não possui itens para finalizar.');
    }

    $submittedTypes = $_POST['item_types'] ?? [];
    if (!is_array($submittedTypes)) {
        throw new InvalidArgumentException('Revise a classificação dos itens importados.');
    }

    $executionItems = [];
    foreach ($items as $item) {
        $type = $submittedTypes[(string) $item->id()] ?? null;
        if (!in_array($type, ['servico', 'outro'], true)) {
            throw new InvalidArgumentException('Classifique todos os itens como serviço ou peça/insumo.');
        }

        $executionItems[] = [
            'type' => $type,
            'ordem_servico_item_id' => $item->id(),
            'reference_id' => $item->referenceId(),
            'description' => $item->description(),
            'unit' => $item->unit(),
            'quantity' => $item->quantity(),
            'unit_price' => $item->unitPrice(),
            'discount' => $item->discount(),
        ];
    }
    $user = $application->authorization()->requireLogin();
    $application->serviceOrderFinalization()->finalizeImportedAcquisition(
        $orderId,
        [
            'execution_items' => $executionItems,
            'saldo_observacao' => 'Cobrança pendente da Prefeitura referente à aquisição importada do SO.',
        ],
        $user->id()
    );
    $session->flash('success', 'OS importada do SO finalizada e encaminhada para cobrança pendente da Prefeitura.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('SO imported order finalization failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível finalizar a OS importada do SO.');
}

os_redirect_back($application);
