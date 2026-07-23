<?php

declare(strict_types=1);

namespace App\ServiceOrder\Service;

use App\Finance\Service\CashManagementService;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ServiceOrderLifecycleService
{
    private const REVERSIBLE_STATUSES = ['agendada', 'em_execucao', 'aguardando_peca'];

    public function __construct(private readonly PDO $connection, private readonly CashManagementService $cash)
    {
    }

    public function reverse(int $orderId, string $reason, int $userId): void
    {
        $reason = $this->requiredReason($reason);
        $this->transactional(function () use ($orderId, $reason, $userId): void {
            $order = $this->lockOrder($orderId);
            if ($order['excluida_em'] !== null) {
                throw new InvalidArgumentException('OS excluída não pode ser estornada.');
            }
            if ($order['status'] !== 'finalizada') {
                throw new InvalidArgumentException('Somente OS finalizada pode ser estornada.');
            }

            $finalization = $this->lockActiveFinalization($orderId);
            if ($finalization === null) {
                throw new InvalidArgumentException('Finalização ativa da OS não encontrada.');
            }

            $account = $this->lockAccountsReceivable($orderId);
            $this->reverseInventory($order, $reason, $userId);
            $paymentIds = $this->reversePaymentsAndCash($order, $reason, $userId);
            $this->cancelReceipts($paymentIds, $reason, $userId);
            $this->reverseAccountsReceivable($account, $reason, $userId);

            $this->connection->prepare(
                'UPDATE ordem_servico_finalizacoes
                    SET ativa = 0,
                        estornado_por = :user_id,
                        estornado_em = CURRENT_TIMESTAMP,
                        motivo_estorno = :reason
                  WHERE id = :id AND ativa = 1'
            )->execute([
                'id' => $finalization['id'],
                'user_id' => $userId,
                'reason' => $reason,
            ]);

            $status = in_array($finalization['status_origem'], self::REVERSIBLE_STATUSES, true)
                ? $finalization['status_origem']
                : 'em_execucao';
            $this->connection->prepare(
                'UPDATE ordens_servico
                    SET status = :status,
                        finalizada_em = NULL,
                        subtotal_servicos = COALESCE(:services, subtotal_servicos),
                        subtotal_produtos = COALESCE(:products, subtotal_produtos),
                        subtotal_outros = COALESCE(:others, subtotal_outros),
                        desconto = COALESCE(:discount, desconto),
                        acrescimo = COALESCE(:increase, acrescimo),
                        total = COALESCE(:total, total)
                  WHERE id = :id'
            )->execute([
                'id' => $orderId,
                'status' => $status,
                'services' => $finalization['subtotal_servicos_origem'],
                'products' => $finalization['subtotal_produtos_origem'],
                'others' => $finalization['subtotal_outros_origem'],
                'discount' => $finalization['desconto_origem'],
                'increase' => $finalization['acrescimo_origem'],
                'total' => $finalization['total_origem'],
            ]);
        });
    }

    public function softDelete(int $orderId, string $reason, int $userId): void
    {
        $reason = $this->requiredReason($reason);
        $this->transactional(function () use ($orderId, $reason, $userId): void {
            $order = $this->lockOrder($orderId);
            if ($order['excluida_em'] !== null) {
                return;
            }
            if ($order['status'] === 'finalizada') {
                throw new InvalidArgumentException('Estorne a OS finalizada antes de excluí-la.');
            }
            if ($this->hasActiveOperationalLinks($orderId)) {
                throw new InvalidArgumentException('A OS possui vínculos operacionais ou financeiros ativos e não pode ser excluída.');
            }

            $this->connection->prepare(
                'UPDATE ordens_servico
                    SET excluida_em = CURRENT_TIMESTAMP,
                        excluida_por = :user_id,
                        motivo_exclusao = :reason,
                        orcamento_liberado = CASE WHEN orcamento_id IS NULL THEN orcamento_liberado ELSE 1 END,
                        orcamento_operacional_chave = NULL
                  WHERE id = :id AND excluida_em IS NULL'
            )->execute([
                'id' => $orderId,
                'user_id' => $userId,
                'reason' => $reason,
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function lockOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new InvalidArgumentException('Identificador de OS inválido.');
        }
        $statement = $this->connection->prepare(
            'SELECT id, numero, status, excluida_em
               FROM ordens_servico
              WHERE id = :id
              FOR UPDATE'
        );
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if ($order === false) {
            throw new InvalidArgumentException('OS não encontrada.');
        }
        return $order;
    }

    /** @return array<string,mixed>|null */
    private function lockActiveFinalization(int $orderId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, status_origem,
                    subtotal_servicos_origem, subtotal_produtos_origem, subtotal_outros_origem,
                    desconto_origem, acrescimo_origem, total_origem
               FROM ordem_servico_finalizacoes
              WHERE ordem_servico_id = :order_id AND ativa = 1
              LIMIT 1
              FOR UPDATE'
        );
        $statement->execute(['order_id' => $orderId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $order */
    private function reverseInventory(array $order, string $reason, int $userId): void
    {
        $statement = $this->connection->prepare(
            "SELECT movement.id, movement.produto_id, movement.quantidade
               FROM estoque_movimentacoes movement
              WHERE movement.ordem_servico_id = :order_id
                AND movement.tipo = 'saida_os'
                AND NOT EXISTS (
                    SELECT 1 FROM estoque_movimentacoes reversal
                     WHERE reversal.estornado_de_id = movement.id
                )
              ORDER BY movement.id
              FOR UPDATE"
        );
        $statement->execute(['order_id' => $order['id']]);
        $movements = $statement->fetchAll();

        $lockProduct = $this->connection->prepare('SELECT estoque FROM produtos WHERE id = :id FOR UPDATE');
        $updateProduct = $this->connection->prepare('UPDATE produtos SET estoque = :stock WHERE id = :id');
        $insertReversal = $this->connection->prepare(
            'INSERT INTO estoque_movimentacoes
                (produto_id, ordem_servico_id, tipo, quantidade, saldo_anterior, saldo_posterior,
                 autorizacao_id, estornado_de_id, usuario_id, observacao)
             VALUES
                (:product_id, :order_id, "estorno", :quantity, :previous, :next,
                 NULL, :source_id, :user_id, :notes)'
        );

        foreach ($movements as $movement) {
            $lockProduct->execute(['id' => $movement['produto_id']]);
            $current = $lockProduct->fetchColumn();
            if ($current === false) {
                throw new InvalidArgumentException('Produto de uma baixa da OS não foi encontrado.');
            }
            $previous = (float) $current;
            $next = $previous + (float) $movement['quantidade'];
            $updateProduct->execute([
                'id' => $movement['produto_id'],
                'stock' => number_format($next, 3, '.', ''),
            ]);
            $insertReversal->execute([
                'product_id' => $movement['produto_id'],
                'order_id' => $order['id'],
                'quantity' => $movement['quantidade'],
                'previous' => number_format($previous, 3, '.', ''),
                'next' => number_format($next, 3, '.', ''),
                'source_id' => $movement['id'],
                'user_id' => $userId,
                'notes' => $this->limit('Estorno da OS ' . ($order['numero'] ?: '#' . $order['id']) . '. Motivo: ' . $reason, 255),
            ]);
        }
    }

    /** @param array<string,mixed> $order @return int[] */
    private function reversePaymentsAndCash(array $order, string $reason, int $userId): array
    {
        $statement = $this->connection->prepare(
            "SELECT id, caixa_movimentacao_id
               FROM ordem_servico_pagamentos
              WHERE ordem_servico_id = :order_id AND status = 'ativo'
              ORDER BY id
              FOR UPDATE"
        );
        $statement->execute(['order_id' => $order['id']]);
        $payments = $statement->fetchAll();

        $reversePayment = $this->connection->prepare(
            "UPDATE ordem_servico_pagamentos
                SET status = 'estornado', estornado_em = CURRENT_TIMESTAMP,
                    estornado_por = :user_id, motivo_estorno = :reason
              WHERE id = :id AND status = 'ativo'"
        );

        $ids = [];
        foreach ($payments as $payment) {
            $ids[] = (int) $payment['id'];
            if ($payment['caixa_movimentacao_id'] !== null) {
                $this->cash->reverseMovement(
                    (int) $payment['caixa_movimentacao_id'], 'os_estorno', (int) $order['id'],
                    $this->limit('Estorno da OS ' . ($order['numero'] ?: '#' . $order['id']) . ': ' . $reason, 255), $userId
                );
            }
            $reversePayment->execute([
                'id' => $payment['id'],
                'user_id' => $userId,
                'reason' => $reason,
            ]);
        }
        return $ids;
    }

    /** @param int[] $paymentIds */
    private function cancelReceipts(array $paymentIds, string $reason, int $userId): void
    {
        if ($paymentIds === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $statement = $this->connection->prepare(
            "UPDATE recibos
                SET status = 'cancelado', cancelado_por = ?, cancelado_em = CURRENT_TIMESTAMP,
                    motivo_cancelamento = ?
              WHERE status = 'emitido' AND pagamento_id IN ($placeholders)"
        );
        $statement->execute([$userId, $reason, ...$paymentIds]);
    }

    /** @return array<string,mixed>|null */
    private function lockAccountsReceivable(int $orderId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, valor_total
               FROM contas_receber
              WHERE ordem_servico_id = :order_id
              FOR UPDATE'
        );
        $statement->execute(['order_id' => $orderId]);
        $account = $statement->fetch();
        return $account === false ? null : $account;
    }

    /** @param array<string,mixed>|null $account */
    private function reverseAccountsReceivable(?array $account, string $reason, int $userId): void
    {
        if ($account === null) {
            return;
        }

        $this->connection->prepare(
            "UPDATE contas_receber SET status = 'estornada' WHERE id = :id"
        )->execute(['id' => $account['id']]);
        $this->connection->prepare(
            'INSERT INTO contas_receber_eventos
                (conta_receber_id, tipo, descricao, valor, data_evento, usuario_id)
             VALUES (:account_id, "estorno", :description, :value, CURRENT_TIMESTAMP, :user_id)'
        )->execute([
            'account_id' => $account['id'],
            'description' => 'Finalização da OS estornada. Motivo: ' . $reason,
            'value' => $account['valor_total'],
            'user_id' => $userId,
        ]);
    }

    private function hasActiveOperationalLinks(int $orderId): bool
    {
        $queries = [
            'SELECT id FROM ordem_servico_finalizacoes WHERE ordem_servico_id = :id AND ativa = 1 LIMIT 1 FOR UPDATE',
            "SELECT id FROM ordem_servico_pagamentos WHERE ordem_servico_id = :id AND status = 'ativo' LIMIT 1 FOR UPDATE",
            "SELECT id FROM contas_receber WHERE ordem_servico_id = :id AND status NOT IN ('estornada','cancelada') LIMIT 1 FOR UPDATE",
            "SELECT movement.id FROM estoque_movimentacoes movement
              WHERE movement.ordem_servico_id = :id AND movement.tipo = 'saida_os'
                AND NOT EXISTS (SELECT 1 FROM estoque_movimentacoes reversal WHERE reversal.estornado_de_id = movement.id)
              LIMIT 1 FOR UPDATE",
            "SELECT receipt.id FROM recibos receipt
                JOIN ordem_servico_pagamentos payment ON payment.id = receipt.pagamento_id
              WHERE payment.ordem_servico_id = :id AND receipt.status = 'emitido'
              LIMIT 1 FOR UPDATE",
        ];
        foreach ($queries as $sql) {
            $statement = $this->connection->prepare($sql);
            $statement->execute(['id' => $orderId]);
            if ($statement->fetch() !== false) {
                return true;
            }
        }
        return false;
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || str_contains($reason, "\0") || $reason !== strip_tags($reason) || $this->textLength($reason) > 255) {
            throw new InvalidArgumentException('Informe um motivo válido com até 255 caracteres.');
        }
        return $reason;
    }

    private function limit(string $value, int $max): string
    {
        if ($this->textLength($value) <= $max) {
            return $value;
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function transactional(callable $callback): void
    {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }
        try {
            $callback();
            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
