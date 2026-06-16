<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientAccountRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ClientAccountService
{
    private ClientAccountRepository $accounts;

    public function __construct(?ClientAccountRepository $accounts = null)
    {
        $this->accounts = $accounts ?? new ClientAccountRepository();
    }

    public function list(int $empresaId, array $filters = []): array
    {
        return $this->accounts->findAll($empresaId, $filters);
    }

    public function summary(int $empresaId, array $filters = []): array
    {
        return $this->accounts->summary($empresaId, $filters);
    }

    public function details(int $empresaId, int $contaId): array
    {
        if ($contaId <= 0) {
            throw new InvalidArgumentException('Conta inválida.');
        }

        $account = $this->accounts->findById($empresaId, $contaId);

        if (!$account) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }

        return [
            'account' => $account,
            'payments' => $this->accounts->payments($empresaId, $contaId),
        ];
    }

    public function pay(int $empresaId, int $usuarioId, int $contaId, array $payload): void
    {
        if ($contaId <= 0) {
            throw new InvalidArgumentException('Conta inválida.');
        }

        $valorPago = $this->parseMoney($payload['valor_pago'] ?? 0);
        if ($valorPago <= 0) {
            throw new InvalidArgumentException('Informe um valor de pagamento maior que zero.');
        }

        $formaPagamento = $this->sanitizePaymentMethod((string)($payload['forma_pagamento'] ?? ''));
        $observacao = trim((string)($payload['observacao'] ?? ''));

        $db = $this->accounts->connection();
        $db->beginTransaction();

        try {
            $account = $this->accounts->findById($empresaId, $contaId, true);

            if (!$account) {
                throw new InvalidArgumentException('Conta não encontrada.');
            }

            if ($account['status'] === 'cancelado') {
                throw new RuntimeException('Conta cancelada não pode receber pagamento.');
            }

            if ($account['status'] === 'pago' || (float)$account['saldo_aberto'] <= 0) {
                throw new RuntimeException('Esta conta já está paga.');
            }

            $saldoAberto = round((float)$account['saldo_aberto'], 2);

            if ($valorPago > $saldoAberto) {
                throw new InvalidArgumentException('O valor pago não pode ser maior que o saldo em aberto.');
            }

            $this->accounts->createPayment($empresaId, [
                'conta_id' => $contaId,
                'cliente_id' => (int)$account['cliente_id'],
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                'valor_pago' => $valorPago,
                'forma_pagamento' => $formaPagamento,
                'observacao' => $observacao,
            ]);

            $this->accounts->applyPayment($empresaId, $contaId, $valorPago);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function settle(int $empresaId, int $usuarioId, int $contaId, string $formaPagamento = 'dinheiro'): void
    {
        if ($contaId <= 0) {
            throw new InvalidArgumentException('Conta inválida.');
        }

        $account = $this->accounts->findById($empresaId, $contaId);

        if (!$account) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }

        $saldoAberto = round((float)$account['saldo_aberto'], 2);

        if ($saldoAberto <= 0) {
            throw new RuntimeException('Esta conta já está paga.');
        }

        $this->pay($empresaId, $usuarioId, $contaId, [
            'valor_pago' => $saldoAberto,
            'forma_pagamento' => $formaPagamento,
            'observacao' => 'Quitação total da conta.',
        ]);
    }

    private function sanitizePaymentMethod(string $method): string
    {
        $method = trim($method);

        $allowed = [
            'pix',
            'dinheiro',
            'credito',
            'debito',
            'transferencia',
            'outro',
        ];

        if (!in_array($method, $allowed, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }

        return $method;
    }

    private function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float)$value, 2);
        }

        $value = trim((string)$value);

        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return round((float)$value, 2);
    }
}