<?php

declare(strict_types=1);

namespace App\ServiceOrder\Service;

use App\Company\DTO\CompanyScope;
use App\Finance\Service\AccountsReceivableManagementService;
use App\Inventory\Service\InventoryManagementService;
use App\ServiceOrder\Repository\ServiceOrderRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ServiceOrderFinalizationService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope,
        private readonly ServiceOrderRepository $orders,
        private readonly InventoryManagementService $inventory,
        private readonly AccountsReceivableManagementService $accounts
    ) {
    }

    /** @return array{order_id:int,order_number:string,balance:string} */
    public function finalize(int $orderId, array $data, int $userId): array
    {
        return $this->finalizeOrder(
            $orderId,
            $data,
            $userId,
            ['em_execucao', 'aguardando_peca', 'agendada']
        );
    }

    /** @return array{order_id:int,order_number:string,balance:string} */
    public function finalizeImportedAcquisition(int $orderId, array $data, int $userId): array
    {
        return $this->finalizeOrder($orderId, $data, $userId, ['aguardando_agendamento']);
    }

    /** @param string[] $allowedStatuses @return array{order_id:int,order_number:string,balance:string} */
    private function finalizeOrder(int $orderId, array $data, int $userId, array $allowedStatuses): array
    {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();
        try {
            $order = $this->orders->lockById($orderId);
            if ($order === null) throw new InvalidArgumentException('OS não encontrada.');
            if (!in_array($order->status(), $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status da OS não permite finalização.');
            }
            if ($this->hasActiveFinalization($orderId)) {
                throw new InvalidArgumentException('OS já finalizada.');
            }

            $items = $this->executionItems($data);
            if ($items === []) throw new InvalidArgumentException('Informe ao menos um item executado.');
            $this->lockExecutionReferences($orderId, $items);

            $totals = ['servico' => 0.0, 'produto' => 0.0, 'outro' => 0.0];
            foreach ($items as $item) {
                $subtotal = max(0.0, $item['quantity'] * $item['unit_price'] - $item['discount']);
                $totals[$item['type']] += $subtotal;
            }

            $discount = $this->money($data['desconto'] ?? '0');
            $increase = $this->money($data['acrescimo'] ?? '0');
            $total = max(0.0, array_sum($totals) - $discount + $increase);
            $paymentValue = $this->money($data['valor_recebido'] ?? '0');
            if ($paymentValue > 0.0) {
                throw new InvalidArgumentException('Finalize a OS sem recebimento e use a ação Pagar OS depois da finalização.');
            }
            if ($total <= 0.0) {
                throw new InvalidArgumentException('O total executado deve ser maior que zero para gerar a conta a receber.');
            }

            $this->connection->prepare(
                'INSERT INTO ordem_servico_finalizacoes
                    (empresa_id, ordem_servico_id, status_origem,
                     subtotal_servicos_origem, subtotal_produtos_origem, subtotal_outros_origem,
                     desconto_origem, acrescimo_origem, total_origem,
                     subtotal_servicos, subtotal_produtos, subtotal_outros,
                     desconto, acrescimo, total_executado, observacao, finalizado_por)
                 VALUES
                    (:company_id, :order_id, :source_status,
                     :source_services, :source_products, :source_others,
                     :source_discount, :source_increase, :source_total,
                     :services, :products, :others, :discount, :increase,
                     :total, :notes, :user_id)'
            )->execute([
                'company_id' => $this->companyScope->id(),
                'order_id' => $orderId,
                'source_status' => $order->status(),
                'source_services' => $order->servicesSubtotal(),
                'source_products' => $order->productsSubtotal(),
                'source_others' => $order->othersSubtotal(),
                'source_discount' => $order->discount(),
                'source_increase' => $order->increase(),
                'source_total' => $order->total(),
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
                    (empresa_id, ordem_servico_id, finalizacao_id, ordem_servico_item_id, tipo, referencia_id,
                     descricao, unidade, quantidade, valor_unitario, desconto, subtotal, adicional, ordem)
                 VALUES
                    (:company_id, :order_id, :finalization_id, :source_item_id, :type, :reference_id,
                     :description, :unit, :quantity, :unit_price, :discount, :subtotal, :additional, :sort_order)'
            );
            foreach ($items as $index => $item) {
                $subtotal = max(0.0, $item['quantity'] * $item['unit_price'] - $item['discount']);
                $insertItem->execute([
                    'company_id' => $this->companyScope->id(),
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

            $this->connection->prepare(
                'UPDATE ordens_servico
                    SET subtotal_servicos = :services,
                        subtotal_produtos = :products,
                        subtotal_outros = :others,
                        desconto = :discount,
                        acrescimo = :increase,
                        total = :total
                  WHERE id = :id AND empresa_id = :company_id'
            )->execute([
                'id' => $orderId,
                'company_id' => $this->companyScope->id(),
                'services' => number_format($totals['servico'], 2, '.', ''),
                'products' => number_format($totals['produto'], 2, '.', ''),
                'others' => number_format($totals['outro'], 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'increase' => number_format($increase, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
            ]);

            $this->accounts->upsertForOrder(
                $orderId,
                number_format($total, 2, '.', ''),
                '0.00',
                $this->optionalDate($data['vencimento_em'] ?? null),
                $this->optionalDate($data['proximo_lembrete_em'] ?? null),
                $this->optionalText($data['saldo_observacao'] ?? null, 1000),
                $userId
            );

            $this->orders->updateStatus($orderId, 'finalizada');
            if ($ownsTransaction) $this->connection->commit();
            return [
                'order_id' => $orderId,
                'order_number' => $order->displayNumber(),
                'balance' => number_format($total, 2, '.', ''),
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    private function hasActiveFinalization(int $orderId): bool
    {
        $statement = $this->connection->prepare('SELECT id FROM ordem_servico_finalizacoes WHERE ordem_servico_id = :id AND empresa_id = :company_id AND ativa = 1 LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $orderId, 'company_id' => $this->companyScope->id()]);
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

    /** @param array<int,array<string,mixed>> $items */
    private function lockExecutionReferences(int $orderId, array $items): void
    {
        $sourceItem = $this->connection->prepare(
            'SELECT id FROM ordem_servico_itens
              WHERE id = :item_id AND ordem_servico_id = :order_id AND empresa_id = :company_id
              FOR UPDATE'
        );
        $references = [
            'produto' => $this->connection->prepare(
                'SELECT id FROM produtos WHERE id = :reference_id AND empresa_id = :company_id AND excluido_em IS NULL FOR UPDATE'
            ),
            'servico' => $this->connection->prepare(
                'SELECT id FROM servicos WHERE id = :reference_id AND empresa_id = :company_id AND excluido_em IS NULL FOR UPDATE'
            ),
        ];

        foreach ($items as $item) {
            if ($item['source_item_id'] !== null) {
                $sourceItem->execute([
                    'item_id' => $item['source_item_id'],
                    'order_id' => $orderId,
                    'company_id' => $this->companyScope->id(),
                ]);
                if ($sourceItem->fetch() === false) {
                    throw new InvalidArgumentException('Item original da OS não encontrado.');
                }
            }
            if ($item['reference_id'] !== null && isset($references[$item['type']])) {
                $references[$item['type']]->execute([
                    'reference_id' => $item['reference_id'],
                    'company_id' => $this->companyScope->id(),
                ]);
                if ($references[$item['type']]->fetch() === false) {
                    throw new InvalidArgumentException('Referência executada não encontrada.');
                }
            }
        }
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
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($text === '' || str_contains($text, "\0") || $text !== strip_tags($text) || $length > $max) throw new InvalidArgumentException('Texto inválido na finalização.');
        return $text;
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if (str_contains($text, "\0") || $length > $max) throw new InvalidArgumentException('Observação inválida.');
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
