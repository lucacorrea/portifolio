<?php

declare(strict_types=1);

namespace App\ServiceOrder\Service;

use App\Finance\Service\AccountsReceivableManagementService;
use App\Inventory\Service\InventoryManagementService;
use App\ServiceOrder\Entity\ServiceOrderItem;
use App\ServiceOrder\Repository\ServiceOrderRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ServiceOrderFinalizationService
{
    private const FINALIZABLE_STATUSES = [
        'agendada',
        'em_execucao',
        'aguardando_peca',
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly ServiceOrderRepository $orders,
        private readonly InventoryManagementService $inventory,
        private readonly AccountsReceivableManagementService $accounts
    ) {
    }

    /**
     * Finaliza a OS usando exclusivamente os itens já gravados no banco.
     *
     * O navegador pode confirmar desconto, acréscimo, vencimento
     * e observações, mas não pode substituir descrição, quantidade,
     * preço ou referência dos itens.
     *
     * @return array{
     *     order_id:int,
     *     order_number:string,
     *     balance:string
     * }
     */
    public function finalize(
        int $orderId,
        array $data,
        int $userId
    ): array {
        if ($orderId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException(
                'OS ou usuário inválido para a finalização.'
            );
        }

        $ownsTransaction = !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $order = $this->orders->lockById($orderId);

            if ($order === null) {
                throw new InvalidArgumentException(
                    'OS não encontrada.'
                );
            }

            if (!in_array(
                $order->status(),
                self::FINALIZABLE_STATUSES,
                true
            )) {
                throw new InvalidArgumentException(
                    'O status atual da OS não permite finalização.'
                );
            }

            if ($this->hasActiveFinalization($orderId)) {
                throw new InvalidArgumentException(
                    'OS já finalizada.'
                );
            }

            /*
             * Os itens são carregados diretamente do banco.
             * Nenhum valor de item enviado pelo navegador é utilizado.
             */
            $items = $this->persistedExecutionItems($orderId);

            if ($items === []) {
                throw new InvalidArgumentException(
                    'A OS não possui itens cadastrados. Edite a OS e inclua ao menos um serviço, produto ou outro item antes de finalizar.'
                );
            }

            $totals = [
                'servico' => 0.0,
                'produto' => 0.0,
                'outro' => 0.0,
            ];

            foreach ($items as $item) {
                $subtotal = max(
                    0.0,
                    ($item['quantity'] * $item['unit_price'])
                    - $item['discount']
                );

                $totals[$item['type']] += $subtotal;
            }

            /*
             * Somente o desconto geral e o acréscimo geral podem ser
             * confirmados ou alterados na finalização.
             */
            $discount = $this->money(
                $data['desconto'] ?? $order->discount()
            );

            $increase = $this->money(
                $data['acrescimo'] ?? $order->increase()
            );

            $grossTotal = array_sum($totals);
            $total = $grossTotal - $discount + $increase;

            if ($discount > ($grossTotal + $increase)) {
                throw new InvalidArgumentException(
                    'O desconto não pode ser maior que o valor total da OS.'
                );
            }

            if ($total <= 0.0) {
                throw new InvalidArgumentException(
                    'O total final da OS deve ser maior que zero para gerar a conta a receber.'
                );
            }

            $finalizationId = $this->insertFinalization(
                $orderId,
                $order->status(),
                [
                    'services' => $order->servicesSubtotal(),
                    'products' => $order->productsSubtotal(),
                    'others' => $order->othersSubtotal(),
                    'discount' => $order->discount(),
                    'increase' => $order->increase(),
                    'total' => $order->total(),
                ],
                $totals,
                $discount,
                $increase,
                $total,
                $this->optionalText(
                    $data['observacao'] ?? null,
                    1000
                ),
                $userId
            );

            $this->insertExecutionItems(
                $orderId,
                $finalizationId,
                $items,
                $userId
            );

            $this->updateOrderTotals(
                $orderId,
                $totals,
                $discount,
                $increase,
                $total
            );

            /*
             * Finalizar não significa receber.
             * A conta nasce com valor recebido zero.
             */
            $this->accounts->upsertForOrder(
                $orderId,
                number_format($total, 2, '.', ''),
                '0.00',
                $this->optionalDate(
                    $data['vencimento_em'] ?? null
                ),
                $this->optionalDate(
                    $data['proximo_lembrete_em'] ?? null
                ),
                $this->optionalText(
                    $data['saldo_observacao'] ?? null,
                    1000
                ),
                $userId
            );

            $this->orders->updateStatus(
                $orderId,
                'finalizada'
            );

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return [
                'order_id' => $orderId,
                'order_number' => $order->displayNumber(),
                'balance' => number_format(
                    $total,
                    2,
                    '.',
                    ''
                ),
            ];
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection->inTransaction()
            ) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    private function hasActiveFinalization(
        int $orderId
    ): bool {
        $statement = $this->connection->prepare(
            'SELECT id
               FROM ordem_servico_finalizacoes
              WHERE ordem_servico_id = :id
                AND ativa = 1
              LIMIT 1
              FOR UPDATE'
        );

        $statement->execute([
            'id' => $orderId,
        ]);

        return $statement->fetch() !== false;
    }

    /**
     * @return array<int,array{
     *     type:string,
     *     source_item_id:int,
     *     reference_id:?int,
     *     description:string,
     *     unit:string,
     *     quantity:float,
     *     unit_price:float,
     *     discount:float,
     *     additional:bool,
     *     authorization_id:?int
     * }>
     */
    private function persistedExecutionItems(
        int $orderId
    ): array {
        $storedItems = $this->orders->findItems($orderId);
        $items = [];

        foreach ($storedItems as $storedItem) {
            if (!$storedItem instanceof ServiceOrderItem) {
                continue;
            }

            $type = $storedItem->type();

            if (!in_array(
                $type,
                ['servico', 'produto', 'outro'],
                true
            )) {
                throw new InvalidArgumentException(
                    'A OS possui item com tipo inválido.'
                );
            }

            $description = $this->requiredText(
                $storedItem->displayDescription(),
                255
            );

            $unit = $this->requiredText(
                $storedItem->unit(),
                20
            );

            $quantity = $this->quantity(
                $storedItem->quantity()
            );

            $unitPrice = $this->money(
                $storedItem->unitPrice()
            );

            $itemDiscount = $this->money(
                $storedItem->discount()
            );

            $rawSubtotal = $quantity * $unitPrice;

            if ($itemDiscount > $rawSubtotal) {
                throw new InvalidArgumentException(
                    'O desconto de um item da OS é maior que o seu valor.'
                );
            }

            if (
                $type === 'produto'
                && $storedItem->referenceId() === null
            ) {
                throw new InvalidArgumentException(
                    'Existe produto sem referência válida na OS. Edite o item antes de finalizar.'
                );
            }

            $items[] = [
                'type' => $type,
                'source_item_id' => $storedItem->id(),
                'reference_id' => $storedItem->referenceId(),
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $itemDiscount,
                'additional' => false,
                'authorization_id' => null,
            ];
        }

        return $items;
    }

    /**
     * @param array{
     *     services:string,
     *     products:string,
     *     others:string,
     *     discount:string,
     *     increase:string,
     *     total:string
     * } $source
     *
     * @param array{
     *     servico:float,
     *     produto:float,
     *     outro:float
     * } $totals
     */
    private function insertFinalization(
        int $orderId,
        string $sourceStatus,
        array $source,
        array $totals,
        float $discount,
        float $increase,
        float $total,
        ?string $notes,
        int $userId
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO ordem_servico_finalizacoes
                (
                    ordem_servico_id,
                    status_origem,
                    subtotal_servicos_origem,
                    subtotal_produtos_origem,
                    subtotal_outros_origem,
                    desconto_origem,
                    acrescimo_origem,
                    total_origem,
                    subtotal_servicos,
                    subtotal_produtos,
                    subtotal_outros,
                    desconto,
                    acrescimo,
                    total_executado,
                    observacao,
                    finalizado_por
                )
             VALUES
                (
                    :order_id,
                    :source_status,
                    :source_services,
                    :source_products,
                    :source_others,
                    :source_discount,
                    :source_increase,
                    :source_total,
                    :services,
                    :products,
                    :others,
                    :discount,
                    :increase,
                    :total,
                    :notes,
                    :user_id
                )'
        );

        $statement->execute([
            'order_id' => $orderId,
            'source_status' => $sourceStatus,
            'source_services' => $source['services'],
            'source_products' => $source['products'],
            'source_others' => $source['others'],
            'source_discount' => $source['discount'],
            'source_increase' => $source['increase'],
            'source_total' => $source['total'],

            'services' => number_format(
                $totals['servico'],
                2,
                '.',
                ''
            ),

            'products' => number_format(
                $totals['produto'],
                2,
                '.',
                ''
            ),

            'others' => number_format(
                $totals['outro'],
                2,
                '.',
                ''
            ),

            'discount' => number_format(
                $discount,
                2,
                '.',
                ''
            ),

            'increase' => number_format(
                $increase,
                2,
                '.',
                ''
            ),

            'total' => number_format(
                $total,
                2,
                '.',
                ''
            ),

            'notes' => $notes,
            'user_id' => $userId,
        ]);

        $finalizationId = (int) $this->connection
            ->lastInsertId();

        if ($finalizationId <= 0) {
            throw new InvalidArgumentException(
                'Não foi possível registrar a finalização da OS.'
            );
        }

        return $finalizationId;
    }

    /**
     * @param array<int,array{
     *     type:string,
     *     source_item_id:int,
     *     reference_id:?int,
     *     description:string,
     *     unit:string,
     *     quantity:float,
     *     unit_price:float,
     *     discount:float,
     *     additional:bool,
     *     authorization_id:?int
     * }> $items
     */
    private function insertExecutionItems(
        int $orderId,
        int $finalizationId,
        array $items,
        int $userId
    ): void {
        $insertItem = $this->connection->prepare(
            'INSERT INTO ordem_servico_execucao_itens
                (
                    ordem_servico_id,
                    finalizacao_id,
                    ordem_servico_item_id,
                    tipo,
                    referencia_id,
                    descricao,
                    unidade,
                    quantidade,
                    valor_unitario,
                    desconto,
                    subtotal,
                    adicional,
                    ordem
                )
             VALUES
                (
                    :order_id,
                    :finalization_id,
                    :source_item_id,
                    :type,
                    :reference_id,
                    :description,
                    :unit,
                    :quantity,
                    :unit_price,
                    :discount,
                    :subtotal,
                    :additional,
                    :sort_order
                )'
        );

        foreach ($items as $index => $item) {
            $subtotal = max(
                0.0,
                ($item['quantity'] * $item['unit_price'])
                - $item['discount']
            );

            $insertItem->execute([
                'order_id' => $orderId,
                'finalization_id' => $finalizationId,
                'source_item_id' => $item['source_item_id'],
                'type' => $item['type'],
                'reference_id' => $item['reference_id'],
                'description' => $item['description'],
                'unit' => $item['unit'],

                'quantity' => number_format(
                    $item['quantity'],
                    3,
                    '.',
                    ''
                ),

                'unit_price' => number_format(
                    $item['unit_price'],
                    2,
                    '.',
                    ''
                ),

                'discount' => number_format(
                    $item['discount'],
                    2,
                    '.',
                    ''
                ),

                'subtotal' => number_format(
                    $subtotal,
                    2,
                    '.',
                    ''
                ),

                'additional' => $item['additional'] ? 1 : 0,
                'sort_order' => $index,
            ]);

            /*
             * Produtos vinculados à OS geram baixa de estoque.
             */
            if (
                $item['type'] === 'produto'
                && $item['reference_id'] !== null
            ) {
                $this->inventory->consumeForOrder(
                    $orderId,
                    $item['reference_id'],
                    number_format(
                        $item['quantity'],
                        3,
                        '.',
                        ''
                    ),
                    $userId,
                    $item['authorization_id']
                );
            }
        }
    }

    /**
     * @param array{
     *     servico:float,
     *     produto:float,
     *     outro:float
     * } $totals
     */
    private function updateOrderTotals(
        int $orderId,
        array $totals,
        float $discount,
        float $increase,
        float $total
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE ordens_servico
                SET subtotal_servicos = :services,
                    subtotal_produtos = :products,
                    subtotal_outros = :others,
                    desconto = :discount,
                    acrescimo = :increase,
                    total = :total
              WHERE id = :id'
        );

        $statement->execute([
            'id' => $orderId,

            'services' => number_format(
                $totals['servico'],
                2,
                '.',
                ''
            ),

            'products' => number_format(
                $totals['produto'],
                2,
                '.',
                ''
            ),

            'others' => number_format(
                $totals['outro'],
                2,
                '.',
                ''
            ),

            'discount' => number_format(
                $discount,
                2,
                '.',
                ''
            ),

            'increase' => number_format(
                $increase,
                2,
                '.',
                ''
            ),

            'total' => number_format(
                $total,
                2,
                '.',
                ''
            ),
        ]);
    }

    private function quantity(mixed $value): float
    {
        $normalized = str_replace(
            ',',
            '.',
            trim((string) $value)
        );

        if (
            preg_match(
                '/^\d+(?:\.\d{1,3})?$/',
                $normalized
            ) !== 1
            || (float) $normalized <= 0.0
        ) {
            throw new InvalidArgumentException(
                'Quantidade inválida em um item da OS.'
            );
        }

        return (float) $normalized;
    }

    private function money(mixed $value): float
    {
        $normalized = str_replace(
            ' ',
            '',
            trim((string) $value)
        );

        if ($normalized === '') {
            return 0.0;
        }

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(
                ',',
                '.',
                str_replace('.', '', $normalized)
            );
        }

        if (
            preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $normalized
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Valor monetário inválido na finalização.'
            );
        }

        return (float) $normalized;
    }

    private function requiredText(
        mixed $value,
        int $maxLength
    ): string {
        $text = trim((string) $value);

        $length = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);

        if (
            $text === ''
            || str_contains($text, "\0")
            || $text !== strip_tags($text)
            || $length > $maxLength
        ) {
            throw new InvalidArgumentException(
                'Existe texto inválido em um item da OS.'
            );
        }

        return $text;
    }

    private function optionalText(
        mixed $value,
        int $maxLength
    ): ?string {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);

        if (
            str_contains($text, "\0")
            || $text !== strip_tags($text)
            || $length > $maxLength
        ) {
            throw new InvalidArgumentException(
                'Observação inválida.'
            );
        }

        return $text;
    }

    private function optionalDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $text
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d') !== $text
        ) {
            throw new InvalidArgumentException(
                'Data inválida.'
            );
        }

        return $text;
    }
}