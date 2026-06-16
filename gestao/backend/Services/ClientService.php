<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\ClientAccountRepository;
use App\Repositories\ClientRepository;
use InvalidArgumentException;

final class ClientService
{
    private ClientRepository $clients;
    private ClientAccountRepository $accounts;

    public function __construct(?ClientRepository $clients = null, ?ClientAccountRepository $accounts = null)
    {
        $this->clients = $clients ?? new ClientRepository();
        $this->accounts = $accounts ?? new ClientAccountRepository();
    }

    public function list(int $empresaId, string $query = ''): array
    {
        return $this->clients->findAll($empresaId, $query);
    }

    public function details(int $empresaId, int $id): ?array
    {
        $client = $this->clients->findById($empresaId, $id);

        if (!$client) {
            return null;
        }

        $client['accounts'] = $this->accounts->findOpenByClient($empresaId, $id);

        return $client;
    }

    public function find(int $empresaId, int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Cliente inválido.');
        }

        return $this->clients->findById($empresaId, $id);
    }

    public function save(int $empresaId, array $payload): array
    {
        $id = (int)($payload['id'] ?? 0);
        $name = trim((string)($payload['name'] ?? $payload['nome'] ?? ''));

        if (!Validator::required($name) || !Validator::max($name, 180)) {
            throw new InvalidArgumentException('Informe um nome de cliente válido.');
        }

        $phone = trim((string)($payload['phone'] ?? $payload['telefone'] ?? ''));
        if (!Validator::max($phone, 30)) {
            throw new InvalidArgumentException('O telefone deve ter no máximo 30 caracteres.');
        }

        $cpfCnpj = trim((string)($payload['cpf'] ?? $payload['cpf_cnpj'] ?? ''));
        if (!Validator::max($cpfCnpj, 20)) {
            throw new InvalidArgumentException('O CPF/CNPJ deve ter no máximo 20 caracteres.');
        }

        $address = trim((string)($payload['address'] ?? $payload['endereco'] ?? ''));
        if (!Validator::max($address, 255)) {
            throw new InvalidArgumentException('O endereço deve ter no máximo 255 caracteres.');
        }

        $data = [
            'nome' => $name,
            'telefone' => $phone,
            'cpf_cnpj' => $cpfCnpj,
            'endereco' => $address,
            'observacao' => trim((string)($payload['note'] ?? $payload['observacao'] ?? $payload['observation'] ?? '')),
        ];

        if ($id > 0) {
            $this->clients->update($empresaId, $id, $data);
        } else {
            $id = $this->clients->create($empresaId, $data);
        }

        return $this->find($empresaId, $id) ?? [];
    }

    public function inactivate(int $empresaId, int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Cliente inválido.');
        }

        $this->clients->inactivate($empresaId, $id);
    }

    public function registerPayment(int $empresaId, int $usuarioId, array $payload): void
    {
        $accountId = (int)($payload['accountId'] ?? $payload['conta_id'] ?? 0);
        $amount = (float)($payload['amount'] ?? $payload['valor'] ?? 0);
        $method = $this->mapPaymentMethod((string)($payload['method'] ?? $payload['metodo'] ?? 'pix'));
        $newDueDate = trim((string)($payload['newDueDate'] ?? $payload['novo_vencimento'] ?? ''));

        if ($accountId <= 0 || !Validator::money($amount, 0.01)) {
            throw new InvalidArgumentException('Pagamento inválido.');
        }

        if (!Validator::date($newDueDate)) {
            throw new InvalidArgumentException('Novo vencimento inválido.');
        }

        $db = $this->accounts->connection();
        $db->beginTransaction();

        try {
            $this->accounts->registerPayment(
                $empresaId,
                $accountId,
                $usuarioId,
                $amount,
                $method,
                $newDueDate ?: null,
                trim((string)($payload['note'] ?? $payload['observacao'] ?? ''))
            );
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function warningMessage(int $empresaId, int $id): ?array
    {
        $client = $this->details($empresaId, $id);

        if (!$client) {
            return null;
        }

        $message = sprintf(
            'Olá, %s. Consta um saldo em aberto de R$ %s%s.',
            $client['name'],
            number_format((float)$client['debt'], 2, ',', '.'),
            $client['due'] ? ' com vencimento em ' . date('d/m/Y', strtotime((string)$client['due'])) : ''
        );

        return [
            'client' => $client,
            'message' => $message,
        ];
    }


    private function mapPaymentMethod(string $method): string
    {
        return [
            'PIX' => 'pix',
            'pix' => 'pix',
            'Crédito' => 'credito',
            'credito' => 'credito',
            'Débito' => 'debito',
            'debito' => 'debito',
            'Dinheiro' => 'dinheiro',
            'dinheiro' => 'dinheiro',
        ][$method] ?? 'pix';
    }
}
