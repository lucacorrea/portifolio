<?php

declare(strict_types=1);

use App\CRM\DTO\ClientFormData;

require __DIR__ . '/cliente-action-common.php';

client_require_post_request();

$rawClientId = trim((string) ($_POST['id'] ?? ''));
$isEditing = $rawClientId !== '';
$requiredPermission = $isEditing ? 'cliente.editar' : 'cliente.criar';
[$application, $session] = client_action_context($requiredPermission);

try {
    $clientId = $isEditing ? client_posted_positive_int('id') : null;
    $service = $application->clientManagement();
    $payload = $_POST;

    if ($clientId !== null) {
        $payload['status'] = $service->getClient($clientId)->status();
    }

    $data = ClientFormData::fromArray($payload);

    if ($clientId === null) {
        $client = $service->createClient($data);
        $session->flash('success', 'Cliente cadastrado com o código ' . $client->displayCode() . '.');
    } else {
        $service->updateClient($clientId, $data);
        $session->flash('success', 'Cliente atualizado com sucesso.');
    }
} catch (InvalidArgumentException $exception) {
    client_store_form_recovery($isEditing ? 'edit' : 'create', $_POST, $exception->getMessage());
    $session->flash('danger', $exception->getMessage());
    client_redirect($application, 'clientes.php?modal=' . ($isEditing ? 'edit' : 'create'));
} catch (Throwable $exception) {
    error_log('Client save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar o cliente.');
}

client_redirect($application, 'clientes.php');
