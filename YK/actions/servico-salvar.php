<?php

declare(strict_types=1);

use App\Catalog\DTO\ServiceFormData;

require __DIR__ . '/servico-action-common.php';

service_require_post_request();

$rawServiceId = trim((string) ($_POST['id'] ?? ''));
$isEditing = $rawServiceId !== '';
$requiredPermission = $isEditing ? 'servico.editar' : 'servico.criar';

[$application, $session] = service_action_context($requiredPermission);
$canChangePrice = $application->authorization()->can('servico.alterar_preco');

try {
    $serviceId = $isEditing ? service_posted_positive_int('id') : null;
    $manager = $application->serviceManagement();
    $existing = $serviceId !== null ? $manager->getService($serviceId) : null;

    $form = [
        'name' => $_POST['name'] ?? '',
        'category' => $_POST['category'] ?? '',
        'compatible_equipment' => $_POST['compatible_equipment'] ?? '',
        'duration_minutes' => $_POST['duration_minutes'] ?? 0,
        'value' => $canChangePrice ? ($_POST['value'] ?? '0') : '0',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'ativo',
    ];
    foreach ([
        'tax_code','nbs','fiscal_description','municipality_code','iss_taxation','iss_withheld','iss_rate',
        'special_regime','iss_enforceability','pis_service_cst','cofins_service_cst',
        'pis_service_rate','cofins_service_rate','ibs_cbs_cst','ibs_cbs_classification',
        'operation_indicator','nfse_purpose','operation_type',
    ] as $field) {
        $form[$field] = $_POST[$field] ?? '';
    }
    $data = ServiceFormData::fromArray($form);

    if ($existing !== null) {
        $data = $data->withValue($canChangePrice ? $data->value() : $existing->value());
        $manager->updateService($serviceId, $data);
        $session->flash('success', 'Serviço atualizado com sucesso.');
    } else {
        $service = $manager->createService($data);
        $session->flash('success', 'Serviço cadastrado com o código ' . $service->displayCode() . '.');
    }
} catch (InvalidArgumentException $exception) {
    $recovery = [
        'id' => $rawServiceId,
        'name' => $_POST['name'] ?? '',
        'category' => $_POST['category'] ?? '',
        'compatible_equipment' => $_POST['compatible_equipment'] ?? '',
        'duration_minutes' => $_POST['duration_minutes'] ?? 0,
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'ativo',
    ];

    if ($canChangePrice) {
        $recovery['value'] = $_POST['value'] ?? '0';
    }
    foreach ([
        'tax_code','nbs','fiscal_description','municipality_code','iss_taxation','iss_withheld','iss_rate',
        'special_regime','iss_enforceability','pis_service_cst','cofins_service_cst',
        'pis_service_rate','cofins_service_rate','ibs_cbs_cst','ibs_cbs_classification',
        'operation_indicator','nfse_purpose','operation_type',
    ] as $field) {
        $recovery[$field] = $_POST[$field] ?? '';
    }

    service_store_form_recovery($isEditing ? 'edit' : 'create', $recovery, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    service_redirect($application, 'servicos.php?modal=' . ($isEditing ? 'edit' : 'create'));
} catch (Throwable $exception) {
    error_log('Service save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o serviço.');
}

service_redirect($application, 'servicos.php');
