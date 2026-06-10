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

    public function save(int $empresaId, array $payload): array
    {
        $id = (int)($payload['id'] ?? 0);
        $name = trim((string)($payload['name'] ?? $payload['nome'] ?? ''));

        if (!Validator::required($name) || !Validator::max($name, 180)) {
            throw new InvalidArgumentException('Informe um nome de cliente válido.');
        }

        $phone = trim((string)($payload['phone'] ?? $payload['telefone'] ?? ''));
        $phone = Validator::normalizeBrazilWhatsapp($phone);

        if ($phone === '' && trim((string)($payload['phone'] ?? $payload['telefone'] ?? '')) !== '') {
            throw new InvalidArgumentException('Informe o WhatsApp no padrão (92) 9151-5710.');
        }

        $data = [
            'nome' => $name,
            'telefone' => $phone,
            'cpf_cnpj' => trim((string)($payload['cpf'] ?? $payload['cpf_cnpj'] ?? '')),
            'endereco' => trim((string)($payload['address'] ?? $payload['endereco'] ?? '')),
            'observacao' => trim((string)($payload['note'] ?? $payload['observacao'] ?? '')),
        ];

        if ($id > 0) {
            $this->clients->update($empresaId, $id, $data);
        } else {
            $id = $this->clients->create($empresaId, $data);
        }

        return $this->details($empresaId, $id) ?? [];
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
