<?php

declare(strict_types=1);

namespace App\Finance\Service;

use InvalidArgumentException;

trait AccountsReceivableOrderPayments
{
    /** @return array{payment_id:int,account_id:int,account_status:string,idempotent:bool} */
    public function registerOrderPayment(
        int $orderId,
        string $value,
        string $form,
        int|string $installmentCount,
        ?string $notes,
        string $paymentToken,
        int $userId
    ): array {
        if ($orderId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('OS ou usuário inválido para o pagamento.');
        }
        $paymentToken = $this->paymentToken($paymentToken);
        $form = $this->paymentForm($form);
        $installmentCount = $this->installmentCount($installmentCount, $form);
        $notes = $this->paymentNotes($notes);
        $amount = $this->moneyToCents($value);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Valor de pagamento inválido para o saldo da OS.');
        }

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();
        try {
            $account = $this->lockAccountByOrder($orderId);
            $existing = $this->lockPaymentByToken($paymentToken);
            if ($existing !== null) {
                if ((int) $existing['ordem_servico_id'] !== $orderId) {
                    throw new InvalidArgumentException('Identificador de pagamento já utilizado em outra OS.');
                }
                if ((string) $existing['status'] !== 'ativo') {
                    throw new InvalidArgumentException('Este pagamento foi estornado e o identificador não pode ser reutilizado.');
                }
                if (
                    $this->moneyToCents((string) $existing['valor']) !== $amount
                    || (string) $existing['forma_pagamento'] !== $form
                    || (int) $existing['quantidade_parcelas'] !== $installmentCount
                    || ($existing['observacao'] === null ? null : (string) $existing['observacao']) !== $notes
                ) {
                    throw new InvalidArgumentException('O identificador já pertence a um pagamento com dados diferentes.');
                }
                if ($ownsTransaction) $this->connection->commit();
                return [
                    'payment_id' => (int) $existing['id'],
                    'account_id' => (int) $account['id'],
                    'account_status' => (string) $account['status'],
                    'idempotent' => true,
                ];
            }

            if ((string) $account['os_status'] !== 'finalizada' || $account['os_excluida_em'] !== null) {
                throw new InvalidArgumentException('Somente OS finalizada e ativa pode ser paga.');
            }
            if (!in_array((string) $account['status'], self::ELIGIBLE_PAYMENT_STATUSES, true)) {
                throw new InvalidArgumentException('A situação da conta não permite novo pagamento.');
            }
            if ($amount > $this->moneyToCents((string) $account['saldo'])) {
                throw new InvalidArgumentException('Valor de pagamento inválido para o saldo da OS.');
            }

            $paymentId = $this->applyPaymentToLockedAccount(
                $account,
                $amount,
                $form,
                $notes,
                $userId,
                $installmentCount,
                $paymentToken,
                'Recebimento da OS ' . ((string) ($account['os_numero'] ?: ('#' . $orderId)))
            );
            $status = $amount === $this->moneyToCents((string) $account['saldo']) ? 'paga' : 'parcial';
            if ($ownsTransaction) $this->connection->commit();

            return [
                'payment_id' => $paymentId,
                'account_id' => (int) $account['id'],
                'account_status' => $status,
                'idempotent' => false,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function lockAccountByOrder(int $orderId): array
    {
        $statement = $this->connection->prepare(
            'SELECT cr.*, os.numero AS os_numero, os.status AS os_status,
                    os.excluida_em AS os_excluida_em
               FROM contas_receber cr
               JOIN ordens_servico os ON os.id = cr.ordem_servico_id
              WHERE cr.ordem_servico_id = :order_id
              FOR UPDATE'
        );
        $statement->execute(['order_id' => $orderId]);
        $row = $statement->fetch();
        if ($row === false) throw new InvalidArgumentException('Conta a receber da OS não encontrada.');
        return $row;
    }

    /** @return array<string,mixed>|null */
    private function lockPaymentByToken(string $paymentToken): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, ordem_servico_id, valor, forma_pagamento, quantidade_parcelas,
                    observacao, status
               FROM ordem_servico_pagamentos
              WHERE payment_token = :payment_token
              LIMIT 1
              FOR UPDATE'
        );
        $statement->execute(['payment_token' => $paymentToken]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    private function paymentToken(string $paymentToken): string
    {
        $paymentToken = trim($paymentToken);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{15,63}$/', $paymentToken) !== 1) {
            throw new InvalidArgumentException('Identificador idempotente do pagamento inválido.');
        }
        return $paymentToken;
    }

    private function installmentCount(int|string $installmentCount, string $form): int
    {
        $installmentCount = filter_var($installmentCount, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 60],
        ]);
        if (!is_int($installmentCount)) {
            throw new InvalidArgumentException('Informe uma quantidade de parcelas entre 1 e 60.');
        }
        if (!in_array($form, ['boleto', 'cartao_credito'], true) && $installmentCount !== 1) {
            throw new InvalidArgumentException('A forma de pagamento selecionada deve possuir exatamente uma parcela.');
        }
        return $installmentCount;
    }
}
