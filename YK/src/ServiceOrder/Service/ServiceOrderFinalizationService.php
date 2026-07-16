<?php

declare(strict_types=1);

namespace App\ServiceOrder\Service;

use App\Finance\Service\AccountsReceivableManagementService;
use App\Finance\Service\CashManagementService;
use App\Inventory\Service\InventoryManagementService;
use App\ServiceOrder\Repository\ServiceOrderRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ServiceOrderFinalizationService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ServiceOrderRepository $orders,
        private readonly InventoryManagementService $inventory,
        private readonly CashManagementService $cash,
        private readonly AccountsReceivableManagementService $accounts
    ) {
    }

    public function finalize(int $orderId, array $data, int $userId): void
    {
        $this->connection->beginTransaction();
        try {
            $order = $this->orders->lockById($orderId);
            if ($order === null) throw new InvalidArgumentException('OS não encontrada.');
            if (!in_array($order->status(), ['em_execucao', 'aguardando_peca', 'agendada'], true)) {
                throw new InvalidArgumentException('Status da OS não permite finalização.');
            }
            if ($this->hasActiveFinalization($orderId)) {
                throw new InvalidArgumentException('OS já finalizada.');
            }

            $items = $this->executionItems($data);
            if ($items === []) throw new InvalidArgumentException('Informe ao menos um item executado.');

            $totals = ['servico' => 0.0, 'produto' => 0.0, 'outro' => 0.0];
            foreach ($items as $item) {
                $subtotal = max(0.0, $item['quantity'] * $item['unit_price'] - $item['discount']);
                $totals[$item['type']] += $subtotal;
            }

            $discount = $this->money($data['desconto'] ?? '0');
            $increase = $this->money($data['acrescimo'] ?? '0');
            $total = max(0.0, array_sum($totals) - $discount + $increase);
            $paymentValue = $this->money($data['valor_recebido'] ?? '0');
            if ($paymentValue > $total) throw new InvalidArgumentException('Pagamento maior que o total executado.');

            $this->connection->prepare(
                'INSERT INTO ordem_servico_finalizacoes
                    (ordem_servico_id, status_origem, subtotal_servicos, subtotal_produtos, subtotal_outros,
                     desconto, acrescimo, total_executado, observacao, finalizado_por)
                 VALUES
                    (:order_id, :source_status, :services, :products, :others, :discount, :increase,
                     :total, :notes, :user_id)'
            )->execute([
                'order_id' => $orderId,
                'source_status' => $order->status(),
                'services' => number_format($totals['servico'], 2, '.', ''),
                'products' => number_format($totals['produto'], 2, '.', ''),
                'others' => number_format($totals['outro'], 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'increase' => number_format($increase, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'notes' => $this->optionalText($data['observacao'] ?? null, 1000),
                'user_id' => $userId,
            ]);
            $finalizationId = (int) $this->connection->lastInsertId();

            $insertItem = $this->connection->prepare(
                'INSERT INTO ordem_servico_execucao_itens
                    (ordem_servico_id, finalizacao_id, ordem_servico_item_id, tipo, referencia_id,
                     descricao, unidade, quantidade, valor_unitario, desconto, subtotal, adicional, ordem)
                 VALUES
                    (:order_id, :finalization_id, :source_item_id, :type, :reference_id,
                     :description, :unit, :quantity, :unit_price, :discount, :subtotal, :additional, :sort_order)'
            );
            foreach ($items as $index => $item) {
                $subtotal = max(0.0, $item['quantity'] * $item['unit_price'] - $item['discount']);
                $insertItem->execute([
                    'order_id' => $orderId,
                    'finalization_id' => $finalizationId,
                    'source_item_id' => $item['source_item_id'],
                    'type' => $item['type'],
                    'reference_id' => $item['reference_id'],
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'quantity' => number_format($item['quantity'], 3, '.', ''),
                    'unit_price' => number_format($item['unit_price'], 2, '.', ''),
                    'discount' => number_format($item['discount'], 2, '.', ''),
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'additional' => $item['additional'] ? 1 : 0,
                    'sort_order' => $index,
                ]);

                if ($item['type'] === 'produto' && $item['reference_id'] !== null) {
                    $this->inventory->consumeForOrder($orderId, $item['reference_id'], (string) $item['quantity'], $userId, $item['authorization_id']);
                }
            }

            if ($paymentValue > 0.0) {
                $form = (string) ($data['forma_pagamento'] ?? '');
                $cashId = $this->cash->registerEntry(
                    'os_pagamento',
                    $orderId,
                    'Recebimento de OS ' . $order->displayNumber() . ' - Cliente: ' . $order->clientName(),
                    $form,
                    number_format($paymentValue, 2, '.', ''),
                    $userId,
                    new DateTimeImmutable((string) ($data['recebido_em'] ?? 'now'))
                );
                $this->connection->prepare(
                    'INSERT INTO ordem_servico_pagamentos
                        (ordem_servico_id, valor, forma_pagamento, recebido_em, observacao, status, registrado_por, caixa_movimentacao_id)
                     VALUES
                        (:order_id, :value, :form, :received_at, :notes, "ativo", :user_id, :cash_id)'
                )->execute([
                    'order_id' => $orderId,
                    'value' => number_format($paymentValue, 2, '.', ''),
                    'form' => $form,
                    'received_at' => (new DateTimeImmutable((string) ($data['recebido_em'] ?? 'now')))->format('Y-m-d H:i:s'),
                    'notes' => $this->optionalText($data['pagamento_observacao'] ?? null, 255),
                    'user_id' => $userId,
                    'cash_id' => $cashId,
                ]);
            }

            $this->accounts->upsertForOrder(
                $orderId,
                number_format($total, 2, '.', ''),
                number_format($paymentValue, 2, '.', ''),
                $this->optionalDate($data['vencimento_em'] ?? null),
                $this->optionalDate($data['proximo_lembrete_em'] ?? null),
                $this->optionalText($data['saldo_observacao'] ?? null, 1000),
                $userId
            );

            $this->orders->updateStatus($orderId, 'finalizada');
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    private function hasActiveFinalization(int $orderId): bool
    {
        $statement = $this->connection->prepare('SELECT id FROM ordem_servico_finalizacoes WHERE ordem_servico_id = :id AND ativa = 1 LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $orderId]);
        return $statement->fetch() !== false;
    }

    /** @return array<int,array<string,mixed>> */
    private function executionItems(array $data): array
    {
        $rows = is_array($data['execution_items'] ?? null) ? $data['execution_items'] : [];
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row) || trim((string) ($row['descricao'] ?? $row['description'] ?? '')) === '') continue;
            $type = (string) ($row['tipo'] ?? $row['type'] ?? '');
            if (!in_array($type, ['servico', 'produto', 'outro'], true)) throw new InvalidArgumentException('Tipo de execução inválido.');
            $items[] = [
                'type' => $type,
                'source_item_id' => $this->optionalInt($row['ordem_servico_item_id'] ?? null),
                'reference_id' => $this->optionalInt($row['referencia_id'] ?? $row['reference_id'] ?? null),
                'description' => $this->requiredText($row['descricao'] ?? $row['description'] ?? '', 255),
                'unit' => $this->requiredText($row['unidade'] ?? $row['unit'] ?? 'un', 20),
                'quantity' => $this->quantity($row['quantidade'] ?? $row['quantity'] ?? '1'),
                'unit_price' => $this->money($row['valor_unitario'] ?? $row['unit_price'] ?? '0'),
                'discount' => $this->money($row['desconto'] ?? $row['discount'] ?? '0'),
                'additional' => in_array($row['adicional'] ?? $row['additional'] ?? false, [1, '1', true, 'on'], true),
                'authorization_id' => $this->optionalInt($row['autorizacao_id'] ?? null),
            ];
        }
        return $items;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($int) ? $int : null;
    }

    private function quantity(mixed $value): float
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d+(\.\d+)?$/', $value) || (float) $value <= 0.0) throw new InvalidArgumentException('Quantidade inválida.');
        return (float) $value;
    }

    private function money(mixed $value): float
    {
        $value = str_replace(' ', '', trim((string) $value));
        if (str_contains($value, ',')) $value = str_replace(',', '.', str_replace('.', '', $value));
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) throw new InvalidArgumentException('Valor monetário inválido.');
        return (float) $value;
    }

    private function requiredText(mixed $value, int $max): string
    {
        $text = trim((string) $value);
        if ($text === '' || str_contains($text, "\0") || $text !== strip_tags($text) || mb_strlen($text) > $max) throw new InvalidArgumentException('Texto inválido na finalização.');
        return $text;
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (str_contains($text, "\0") || mb_strlen($text) > $max) throw new InvalidArgumentException('Observação inválida.');
        return $text;
    }

    private function optionalDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) throw new InvalidArgumentException('Data inválida.');
        return $text;
    }
}
