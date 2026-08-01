<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;
use App\CRM\Entity\Client;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function client_search_respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function client_search_filter(string $key, int $maximumLength): string
{
    $raw = $_GET[$key] ?? '';
    if (!is_string($raw)) {
        throw new InvalidArgumentException('Filtro inválido.');
    }
    $value = trim($raw);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $maximumLength || str_contains($value, "\0")) {
        throw new InvalidArgumentException('Filtro inválido.');
    }
    return $value;
}

function client_search_document(?string $document, string $personType): string
{
    if ($document === null || $document === '') return '-';
    if ($personType === 'juridica' && strlen($document) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $document) ?? $document;
    }
    if (strlen($document) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $document) ?? $document;
    }
    return $document;
}

function client_search_address(Client $client): string
{
    $address = trim(implode(', ', array_filter([$client->address(), $client->number(), $client->district()])));
    $cityState = trim(implode('/', array_filter([$client->city(), $client->state()])));
    $parts = array_filter([$address, $cityState]);
    return $parts === [] ? 'Endereço não informado' : implode(', ', $parts);
}

function client_search_date(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    client_search_respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Application $application */
    $application = $app['application'];
    $application->session()->start();
    $authorization = $application->authorization();
    $authorization->requireLogin();
    $authorization->requirePermission('cliente.visualizar');
    session_write_close();

    $filters = [
        'search' => client_search_filter('search', 150),
        'type' => client_search_filter('type', 20),
        'city' => client_search_filter('city', 100),
        'status' => client_search_filter('status', 20),
        'limit' => 101,
    ];
    if (!in_array($filters['type'], ['', 'fisica', 'juridica'], true)
        || !in_array($filters['status'], ['', 'ativo', 'inativo'], true)
    ) {
        throw new InvalidArgumentException('Filtro inválido.');
    }

    $clients = $application->clientManagement()->listClients($filters);
    $hasMore = count($clients) > 100;
    $clients = array_slice($clients, 0, 100);
    $canHistory = $authorization->can('cliente.visualizar_historico');
    $canViewBudget = $authorization->can('orcamento.visualizar');
    $clientBudgets = [];
    if ($canHistory && $clients !== []) {
        $clientIds = array_map(static fn(Client $client): int => $client->id(), $clients);
        foreach ($application->budgetManagement()->budgetsByClients($clientIds) as $budget) {
            $clientBudgets[$budget->clientId()][] = $budget;
        }
    }

    $payload = array_map(static function (Client $client) use ($clientBudgets, $canViewBudget): array {
        return [
            'id' => $client->id(), 'code' => $client->displayCode(), 'person_type' => $client->personType(), 'person_type_label' => $client->personTypeLabel(),
            'name' => $client->name(), 'document' => $client->document(), 'document_label' => client_search_document($client->document(), $client->personType()),
            'phone' => $client->phone(), 'whatsapp' => $client->whatsapp(), 'email' => $client->email(), 'address' => $client->address(), 'number' => $client->number(),
            'complement' => $client->complement(), 'district' => $client->district(), 'city' => $client->city(), 'state' => $client->state(), 'zip_code' => $client->zipCode(),
            'full_address' => client_search_address($client), 'notes' => $client->notes(), 'status' => $client->status(), 'status_label' => $client->status() === 'ativo' ? 'Ativo' : 'Inativo',
            'created_at' => client_search_date($client->createdAt()), 'updated_at' => client_search_date($client->updatedAt()),
            'budgets' => array_map(static fn($budget): array => [
                'id' => $budget->id(), 'number' => $budget->displayNumber(), 'issue_date' => $budget->issueDate(), 'valid_until' => $budget->validUntil(),
                'total' => $budget->total(), 'status' => $budget->displayStatus(), 'can_view' => $canViewBudget,
            ], $clientBudgets[$client->id()] ?? []),
        ];
    }, $clients);

    client_search_respond(['ok' => true, 'count' => count($payload), 'has_more' => $hasMore, 'clients' => $payload]);
} catch (AuthenticationException) {
    client_search_respond(['ok' => false, 'error' => 'Sua sessão expirou. Atualize a página e entre novamente.'], 401);
} catch (AuthorizationException) {
    client_search_respond(['ok' => false, 'error' => 'Você não possui permissão para consultar clientes.'], 403);
} catch (InvalidArgumentException $exception) {
    client_search_respond(['ok' => false, 'error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Client AJAX search failed: ' . $exception->getMessage());
    client_search_respond(['ok' => false, 'error' => 'Não foi possível pesquisar os clientes.'], 500);
}
