<?php

declare(strict_types=1);

namespace App\ServiceOrder\Service;

use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\ServiceRepository;
use App\CRM\Repository\ClientRepository;
use App\Sales\Repository\BudgetRepository;
use App\ServiceOrder\DTO\ServiceOrderFormData;
use App\ServiceOrder\DTO\ServiceOrderScheduleData;
use App\ServiceOrder\DTO\ServiceOrderTeamData;
use App\ServiceOrder\DTO\ServiceOrderTeamMemberData;
use App\ServiceOrder\Entity\ServiceOrder;
use App\ServiceOrder\Entity\ServiceOrderItem;
use App\ServiceOrder\Repository\ServiceOrderRepository;
use App\Workforce\Repository\EmployeeRepository;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ServiceOrderManagementService
{
    private const STATUS_REQUIRES_TEAM_AND_SCHEDULE = [
        'agendada',
        'em_deslocamento',
        'em_execucao',
    ];

    private const TRANSITIONS = [
        'rascunho' => [
            'aberta',
            'aguardando_agendamento',
            'cancelada',
        ],
        'aberta' => [
            'aguardando_agendamento',
            'agendada',
            'cancelada',
        ],
        'aguardando_agendamento' => [
            'agendada',
            'cancelada',
        ],
        'agendada' => [
            'em_deslocamento',
            'em_execucao',
            'aguardando_peca',
            'cancelada',
        ],
        'em_deslocamento' => [
            'em_execucao',
            'aguardando_peca',
            'cancelada',
        ],
        'em_execucao' => [
            'aguardando_peca',
            'finalizada',
            'cancelada',
        ],
        'aguardando_peca' => [
            'agendada',
            'em_execucao',
            'cancelada',
        ],
        'finalizada' => [],
        'cancelada' => [],
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly ServiceOrderRepository $orders,
        private readonly EmployeeRepository $employees,
        private readonly ClientRepository $clients,
        private readonly ServiceRepository $services,
        private readonly ProductRepository $products,
        private readonly ?BudgetRepository $budgets = null
    ) {}

    /**
     * @return ServiceOrder[]
     */
    public function listOrders(array $filters = []): array
    {
        return $this->orders->findAll($filters);
    }

    public function orderSummary(): array
    {
        return $this->orders->summary();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function availableApprovedBudgets(): array
    {
        if ($this->budgets === null) {
            return [];
        }

        return $this->budgets->availableApprovedForServiceOrder();
    }

    public function getOrder(int $id): ServiceOrder
    {
        $order = $this->orders->findById($id);

        if ($order === null) {
            throw new InvalidArgumentException(
                'Ordem de serviço não encontrada.'
            );
        }

        return $order;
    }

    /**
     * @return ServiceOrderItem[]
     */
    public function getOrderItems(int $id): array
    {
        $this->getOrder($id);

        return $this->orders->findItems($id);
    }

    public function getOrderTeamMembers(int $id): array
    {
        $this->getOrder($id);

        return $this->orders->findTeamMembers($id);
    }

    /**
     * @param ServiceOrder[] $orders
     *
     * @return array<int,array>
     */
    public function teamMembersForOrders(array $orders): array
    {
        return $this->orders->findTeamMembersForOrders(
            array_map(
                static fn(ServiceOrder $order): int => $order->id(),
                $orders
            )
        );
    }

    public function createOrder(
        ServiceOrderFormData $data,
        ?ServiceOrderTeamData $team,
        ?ServiceOrderScheduleData $schedule
    ): ServiceOrder {
        return $this->transactional(
            function () use ($data, $team, $schedule): ServiceOrder {
                $this->validateReferences($data);

                $this->validateStateRequirements(
                    $data->status(),
                    $team,
                    $schedule
                );

                if ($team !== null && $team->hasMembers()) {
                    $this->validateEmployees($team);
                }

                return $this->orders->create(
                    $data,
                    $team,
                    $schedule?->start(),
                    $schedule?->end()
                );
            }
        );
    }

    public function createOrderFromApprovedBudget(
        int $budgetId,
        ?ServiceOrderTeamData $team,
        ?ServiceOrderScheduleData $schedule,
        bool $draft
    ): ServiceOrder {
        if ($this->budgets === null) {
            throw new InvalidArgumentException(
                'Integração de orçamento indisponível.'
            );
        }

        return $this->transactional(
            function () use (
                $budgetId,
                $team,
                $schedule,
                $draft
            ): ServiceOrder {
                $budget = $this->budgets->lockById($budgetId);

                if ($budget === null) {
                    throw new InvalidArgumentException(
                        'Orçamento não encontrado.'
                    );
                }

                if ($budget->status() !== 'aprovado') {
                    throw new InvalidArgumentException(
                        'Somente orçamento aprovado pode gerar OS.'
                    );
                }

                if (
                    $this->orders->hasOperationalOrderForBudget(
                        $budgetId
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Este orçamento já possui uma OS operacional vinculada.'
                    );
                }

                $budgetItems = $this->budgets->findItems(
                    $budgetId
                );

                if ($budgetItems === []) {
                    throw new InvalidArgumentException(
                        'Orçamento aprovado sem itens não pode gerar OS.'
                    );
                }

                $status = $draft
                    ? 'rascunho'
                    : (
                        $schedule === null
                        ? 'aguardando_agendamento'
                        : 'agendada'
                    );

                $items = [];

                foreach ($budgetItems as $item) {
                    $items[] = [
                        'type' => $item->type(),
                        'origin' => 'orcamento',
                        'reference_id' => $item->referenceId(),
                        'budget_item_id' => $item->id(),
                        'description' => $item->description(),
                        'unit' => $item->unit(),
                        'quantity' => $item->quantity(),
                        'unit_price' => $item->unitPrice(),
                        'discount' => $item->discount(),
                    ];
                }

                $data = ServiceOrderFormData::fromArray([
                    'client_id' => $budget->clientId(),
                    'budget_id' => $budget->id(),
                    'status' => $status,
                    'priority' => 'media',
                    'discount' => $budget->discount(),
                    'increase' => $budget->increase(),
                    'items' => $items,
                ]);

                $this->validateStateRequirements(
                    $data->status(),
                    $team,
                    $schedule
                );

                if ($team !== null && $team->hasMembers()) {
                    $this->validateEmployees($team);
                }

                return $this->orders->create(
                    $data,
                    $team,
                    $schedule?->start(),
                    $schedule?->end()
                );
            }
        );
    }

    public function approveBudgetAndCreateOrder(
        int $budgetId
    ): ServiceOrder {
        if ($this->budgets === null) {
            throw new InvalidArgumentException(
                'Integração de orçamento indisponível.'
            );
        }

        return $this->transactional(
            function () use ($budgetId): ServiceOrder {
                $budget = $this->budgets->lockById($budgetId);

                if ($budget === null) {
                    throw new InvalidArgumentException(
                        'Orçamento não encontrado.'
                    );
                }

                if ($budget->status() === 'recusado') {
                    throw new InvalidArgumentException(
                        'Orçamento recusado não pode ser aprovado.'
                    );
                }

                if (
                    $this->orders->hasOperationalOrderForBudget(
                        $budgetId
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Este orçamento já possui uma OS operacional vinculada.'
                    );
                }

                $budgetItems = $this->budgets->findItems(
                    $budgetId
                );

                if ($budgetItems === []) {
                    throw new InvalidArgumentException(
                        'Não é possível aprovar orçamento sem itens.'
                    );
                }

                if ($budget->status() !== 'aprovado') {
                    $this->budgets->approve($budgetId);
                }

                $items = [];

                foreach ($budgetItems as $item) {
                    $items[] = [
                        'type' => $item->type(),
                        'origin' => 'orcamento',
                        'reference_id' => $item->referenceId(),
                        'budget_item_id' => $item->id(),
                        'description' => $item->description(),
                        'unit' => $item->unit(),
                        'quantity' => $item->quantity(),
                        'unit_price' => $item->unitPrice(),
                        'discount' => $item->discount(),
                    ];
                }

                $data = ServiceOrderFormData::fromArray([
                    'client_id' => $budget->clientId(),
                    'budget_id' => $budget->id(),
                    'status' => 'aguardando_agendamento',
                    'priority' => 'media',
                    'discount' => $budget->discount(),
                    'increase' => $budget->increase(),
                    'notes' => $budget->notes(),
                    'items' => $items,
                ]);

                $order = $this->orders->create(
                    $data,
                    null,
                    null,
                    null
                );

                $this->connection->prepare(
                    'UPDATE ordens_servico
                        SET valor_aprovado_orcamento = :approved_value
                      WHERE id = :order_id'
                )->execute([
                    'approved_value' => $budget->total(),
                    'order_id' => $order->id(),
                ]);

                return $this->getOrder($order->id());
            }
        );
    }

    public function updateOrder(
        int $id,
        ServiceOrderFormData $data,
        ?ServiceOrderTeamData $team = null,
        ?ServiceOrderScheduleData $schedule = null,
        bool $teamSubmitted = false,
        bool $scheduleSubmitted = false
    ): void {
        $this->transactional(
            function () use (
                $id,
                $data,
                $team,
                $schedule,
                $teamSubmitted,
                $scheduleSubmitted
            ): void {
                $order = $this->requireLockedOrder($id);

                $this->assertOrderMutable($order);
                $this->validateReferences($data);

                $effectiveTeam = null;

                if ($teamSubmitted) {
                    $effectiveTeam = $team
                        ?? new ServiceOrderTeamData([]);
                } elseif ($scheduleSubmitted) {
                    $effectiveTeam = $this->teamFromOrder(
                        $order
                    );
                }

                if (
                    $teamSubmitted
                    && $effectiveTeam !== null
                    && $effectiveTeam->hasMembers()
                ) {
                    $this->validateEmployees(
                        $effectiveTeam
                    );
                }

                if (
                    $scheduleSubmitted
                    && $schedule !== null
                    && $effectiveTeam !== null
                ) {
                    $this->validateTeamForScheduledOrder(
                        $effectiveTeam
                    );

                    $this->validateConflicts(
                        $id,
                        $effectiveTeam,
                        $schedule
                    );
                } elseif (
                    $teamSubmitted
                    && $effectiveTeam !== null
                    && $order->scheduledStart() !== null
                    && $order->scheduledEnd() !== null
                ) {
                    $this->validateTeamForScheduledOrder(
                        $effectiveTeam
                    );

                    $this->validateConflicts(
                        $id,
                        $effectiveTeam,
                        new ServiceOrderScheduleData(
                            new DateTimeImmutable(
                                $order->scheduledStart()
                            ),
                            new DateTimeImmutable(
                                $order->scheduledEnd()
                            )
                        )
                    );
                }

                $this->orders->updateCore(
                    $id,
                    $data
                );

                if (
                    $teamSubmitted
                    && $effectiveTeam !== null
                ) {
                    $this->orders->replaceTeam(
                        $id,
                        $effectiveTeam
                    );
                }

                if (
                    $scheduleSubmitted
                    && $schedule !== null
                ) {
                    $this->orders->updateSchedule(
                        $id,
                        $schedule->start(),
                        $schedule->end()
                    );

                    if (
                        in_array(
                            $order->status(),
                            [
                                'rascunho',
                                'aberta',
                                'aguardando_agendamento',
                            ],
                            true
                        )
                    ) {
                        $this->orders->updateStatus(
                            $id,
                            'agendada'
                        );
                    }
                }
            }
        );
    }

    public function assignTeam(
        int $orderId,
        ServiceOrderTeamData $data
    ): void {
        $this->transactional(
            function () use ($orderId, $data): void {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                $this->assertOrderMutable($order);

                if ($data->hasMembers()) {
                    $this->validateEmployees($data);
                }

                $this->validateConflictIfScheduled(
                    $order,
                    $data
                );

                $this->orders->replaceTeam(
                    $orderId,
                    $data
                );
            }
        );
    }

    public function reassignTeam(
        int $orderId,
        ServiceOrderTeamData $team
    ): void {
        $this->assignTeam($orderId, $team);
    }

    public function scheduleOrder(
        int $orderId,
        ServiceOrderScheduleData $data
    ): void {
        $this->reschedule($orderId, $data);
    }

    public function reschedule(
        int $orderId,
        ServiceOrderScheduleData $schedule
    ): void {
        $this->transactional(
            function () use (
                $orderId,
                $schedule
            ): void {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                $this->assertOrderMutable($order);

                $team = $this->teamFromOrder($order);

                $this->validateTeamForScheduledOrder(
                    $team
                );

                $this->validateConflicts(
                    $orderId,
                    $team,
                    $schedule
                );

                $this->orders->updateSchedule(
                    $orderId,
                    $schedule->start(),
                    $schedule->end()
                );

                if (
                    in_array(
                        $order->status(),
                        [
                            'rascunho',
                            'aberta',
                            'aguardando_agendamento',
                        ],
                        true
                    )
                ) {
                    $this->orders->updateStatus(
                        $orderId,
                        'agendada'
                    );
                }
            }
        );
    }

    public function assignTeamAndSchedule(
        int $orderId,
        ServiceOrderTeamData $team,
        ServiceOrderScheduleData $schedule
    ): void {
        $this->transactional(
            function () use (
                $orderId,
                $team,
                $schedule
            ): void {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                $this->assertOrderMutable($order);

                $this->validateTeamForScheduledOrder(
                    $team
                );

                $this->validateConflicts(
                    $orderId,
                    $team,
                    $schedule
                );

                $this->orders->replaceTeam(
                    $orderId,
                    $team
                );

                $this->orders->updateSchedule(
                    $orderId,
                    $schedule->start(),
                    $schedule->end()
                );

                $this->orders->updateStatus(
                    $orderId,
                    'agendada'
                );
            }
        );
    }

    public function changeStatus(
        int $orderId,
        string $status
    ): void {
        $this->transactional(
            function () use (
                $orderId,
                $status
            ): void {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                $this->assertTransition(
                    $order->status(),
                    $status
                );

                $this->validateOrderCanHaveStatus(
                    $order,
                    $status
                );

                $this->orders->updateStatus(
                    $orderId,
                    $status
                );
            }
        );
    }

    /**
     * Atualiza somente a quantidade de um produto durante
     * a conferência da finalização da OS.
     *
     * O método:
     * - bloqueia a OS e o item durante a operação;
     * - permite alterar apenas produtos;
     * - recalcula o subtotal do item;
     * - recalcula os subtotais e o total da OS;
     * - executa tudo dentro de uma transação.
     *
     * @return array{
     *     order_id:int,
     *     item_id:int,
     *     quantity:string,
     *     subtotal:string,
     *     order_total:string
     * }
     */
    public function updateItemQuantityForFinalization(
        int $orderId,
        int $itemId,
        string $quantity
    ): array {
        if ($orderId <= 0 || $itemId <= 0) {
            throw new InvalidArgumentException(
                'OS ou item inválido.'
            );
        }

        return $this->transactional(
            function () use (
                $orderId,
                $itemId,
                $quantity
            ): array {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                $this->assertOrderMutable($order);

                if (
                    !in_array(
                        $order->status(),
                        [
                            'agendada',
                            'em_execucao',
                            'aguardando_peca',
                        ],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'O status atual da OS não permite alterar a quantidade durante a finalização.'
                    );
                }

                $normalizedQuantity =
                    $this->normalizeFinalizationQuantity(
                        $quantity
                    );

                $statement = $this->connection->prepare(
                    'SELECT
                        id,
                        tipo,
                        valor_unitario,
                        desconto
                     FROM ordem_servico_itens
                     WHERE id = :item_id
                       AND ordem_servico_id = :order_id
                     LIMIT 1
                     FOR UPDATE'
                );

                $statement->execute([
                    'item_id' => $itemId,
                    'order_id' => $orderId,
                ]);

                $item = $statement->fetch();

                if ($item === false) {
                    throw new InvalidArgumentException(
                        'Item não encontrado nesta OS.'
                    );
                }

                if (
                    (string) ($item['tipo'] ?? '')
                    !== 'produto'
                ) {
                    throw new InvalidArgumentException(
                        'Somente a quantidade de produtos pode ser ajustada durante a finalização.'
                    );
                }

                $unitPrice = (float) (
                    $item['valor_unitario'] ?? 0
                );

                $itemDiscount = (float) (
                    $item['desconto'] ?? 0
                );

                $grossSubtotal =
                    ((float) $normalizedQuantity)
                    * $unitPrice;

                if ($itemDiscount > $grossSubtotal) {
                    throw new InvalidArgumentException(
                        'A quantidade informada deixa o desconto do item maior que seu valor.'
                    );
                }

                $subtotal = max(
                    0.0,
                    $grossSubtotal - $itemDiscount
                );

                $updateItem = $this->connection->prepare(
                    'UPDATE ordem_servico_itens
                        SET quantidade = :quantity,
                            subtotal = :subtotal
                      WHERE id = :item_id
                        AND ordem_servico_id = :order_id'
                );

                $updateItem->execute([
                    'quantity' => $normalizedQuantity,

                    'subtotal' => number_format(
                        $subtotal,
                        2,
                        '.',
                        ''
                    ),

                    'item_id' => $itemId,
                    'order_id' => $orderId,
                ]);

                $totalsStatement =
                    $this->connection->prepare(
                        "SELECT
                            COALESCE(
                                SUM(
                                    CASE
                                        WHEN tipo = 'servico'
                                        THEN subtotal
                                        ELSE 0
                                    END
                                ),
                                0
                            ) AS services_total,

                            COALESCE(
                                SUM(
                                    CASE
                                        WHEN tipo = 'produto'
                                        THEN subtotal
                                        ELSE 0
                                    END
                                ),
                                0
                            ) AS products_total,

                            COALESCE(
                                SUM(
                                    CASE
                                        WHEN tipo = 'outro'
                                        THEN subtotal
                                        ELSE 0
                                    END
                                ),
                                0
                            ) AS others_total
                         FROM ordem_servico_itens
                         WHERE ordem_servico_id = :order_id"
                    );

                $totalsStatement->execute([
                    'order_id' => $orderId,
                ]);

                $totals =
                    $totalsStatement->fetch() ?: [];

                $servicesTotal = (float) (
                    $totals['services_total'] ?? 0
                );

                $productsTotal = (float) (
                    $totals['products_total'] ?? 0
                );

                $othersTotal = (float) (
                    $totals['others_total'] ?? 0
                );

                $orderTotal = max(
                    0.0,
                    $servicesTotal
                        + $productsTotal
                        + $othersTotal
                        - (float) $order->discount()
                        + (float) $order->increase()
                );

                $updateOrder =
                    $this->connection->prepare(
                        'UPDATE ordens_servico
                            SET subtotal_servicos = :services,
                                subtotal_produtos = :products,
                                subtotal_outros = :others,
                                total = :total
                          WHERE id = :order_id'
                    );

                $updateOrder->execute([
                    'services' => number_format(
                        $servicesTotal,
                        2,
                        '.',
                        ''
                    ),

                    'products' => number_format(
                        $productsTotal,
                        2,
                        '.',
                        ''
                    ),

                    'others' => number_format(
                        $othersTotal,
                        2,
                        '.',
                        ''
                    ),

                    'total' => number_format(
                        $orderTotal,
                        2,
                        '.',
                        ''
                    ),

                    'order_id' => $orderId,
                ]);

                return [
                    'order_id' => $orderId,
                    'item_id' => $itemId,
                    'quantity' => $normalizedQuantity,

                    'subtotal' => number_format(
                        $subtotal,
                        2,
                        '.',
                        ''
                    ),

                    'order_total' => number_format(
                        $orderTotal,
                        2,
                        '.',
                        ''
                    ),
                ];
            }
        );
    }

    public function finalize(int $orderId): void
    {
        $this->changeStatus(
            $orderId,
            'finalizada'
        );
    }

    public function cancel(int $orderId): void
    {
        $this->changeStatus(
            $orderId,
            'cancelada'
        );
    }

    public function cancelWithDetails(
        int $orderId,
        string $option,
        string $reason,
        ?string $notes,
        int $userId
    ): void {
        $this->transactional(
            function () use (
                $orderId,
                $option,
                $reason,
                $notes,
                $userId
            ): void {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                if (
                    in_array(
                        $order->status(),
                        [
                            'finalizada',
                            'cancelada',
                        ],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'OS finalizada ou cancelada não pode ser cancelada novamente.'
                    );
                }

                if (
                    !in_array(
                        $option,
                        [
                            'definitivo',
                            'liberar_orcamento',
                            'criar_substituta',
                        ],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Destino do orçamento inválido.'
                    );
                }

                $reason = $this->cleanText(
                    $reason,
                    150,
                    'motivo'
                );

                $notes = $notes === null
                    ? null
                    : $this->cleanOptionalText(
                        $notes,
                        1000
                    );

                $releaseBudget = in_array(
                    $option,
                    [
                        'liberar_orcamento',
                        'criar_substituta',
                    ],
                    true
                )
                    ? 1
                    : 0;

                $statement =
                    $this->connection->prepare(
                        'INSERT INTO ordem_servico_cancelamentos
                            (
                                ordem_servico_id,
                                opcao,
                                motivo,
                                observacao,
                                orcamento_liberado,
                                cancelado_por
                            )
                         VALUES
                            (
                                :order_id,
                                :option,
                                :reason,
                                :notes,
                                :release_budget,
                                :user_id
                            )'
                    );

                $statement->execute([
                    'order_id' => $orderId,
                    'option' => $option,
                    'reason' => $reason,
                    'notes' => $notes,
                    'release_budget' => $releaseBudget,
                    'user_id' => $userId,
                ]);

                $cancellationId = (int) (
                    $this->connection->lastInsertId()
                );

                $this->connection->prepare(
                    "UPDATE ordens_servico
                        SET status = 'cancelada',
                            cancelada_em = COALESCE(
                                cancelada_em,
                                CURRENT_TIMESTAMP
                            ),
                            orcamento_liberado = :release_budget
                      WHERE id = :order_id"
                )->execute([
                    'order_id' => $orderId,
                    'release_budget' => $releaseBudget,
                ]);

                $this->orders->syncOperationalBudgetKey(
                    $orderId
                );

                if ($option === 'criar_substituta') {
                    $replacement =
                        $this->createReplacementOrder(
                            $order
                        );

                    $this->connection->prepare(
                        'UPDATE ordem_servico_cancelamentos
                            SET ordem_substituta_id = :replacement_id
                          WHERE id = :cancellation_id'
                    )->execute([
                        'replacement_id' => $replacement->id(),
                        'cancellation_id' => $cancellationId,
                    ]);

                    $this->connection->prepare(
                        'UPDATE ordens_servico
                            SET ordem_substituta_id = :replacement_id
                          WHERE id = :order_id'
                    )->execute([
                        'replacement_id' => $replacement->id(),
                        'order_id' => $orderId,
                    ]);
                }
            }
        );
    }

    public function reopen(int $orderId): void
    {
        $this->transactional(
            function () use ($orderId): void {
                $order = $this->requireLockedOrder(
                    $orderId
                );

                if (
                    !in_array(
                        $order->status(),
                        [
                            'finalizada',
                            'cancelada',
                        ],
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Apenas OS finalizada ou cancelada pode ser reaberta.'
                    );
                }

                $this->assertOrderCanReopen($order);

                $this->orders->updateStatus(
                    $orderId,
                    'aberta'
                );

                if (
                    $order->status() === 'cancelada'
                    && $order->budgetId() !== null
                ) {
                    $this->orders->markBudgetReleased(
                        $orderId,
                        false
                    );
                }
            }
        );
    }

    /**
     * @return ServiceOrder[]
     */
    public function calendarBetween(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $filters = []
    ): array {
        if ($end <= $start) {
            throw new InvalidArgumentException(
                'Período inválido para a agenda.'
            );
        }

        return $this->orders->findScheduledBetween(
            $start,
            $end,
            $filters
        );
    }

    /**
     * @return array<string,ServiceOrder[]>
     */
    public function weekSchedule(
        DateTimeImmutable $weekStart,
        array $filters = []
    ): array {
        $monday = $weekStart
            ->modify('monday this week')
            ->setTime(0, 0);

        $nextMonday = $monday->add(
            new DateInterval('P7D')
        );

        $orders = $this->calendarBetween(
            $monday,
            $nextMonday,
            $filters
        );

        $grouped = [];

        foreach ($orders as $order) {
            $day = $order->scheduledStart() === null
                ? $monday->format('Y-m-d')
                : (
                    new DateTimeImmutable(
                        $order->scheduledStart()
                    )
                )->format('Y-m-d');

            $grouped[$day][] = $order;
        }

        return $grouped;
    }

    private function requireLockedOrder(
        int $orderId
    ): ServiceOrder {
        $order = $this->orders->lockById(
            $orderId
        );

        if ($order === null) {
            throw new InvalidArgumentException(
                'Ordem de serviço não encontrada.'
            );
        }

        return $order;
    }

    private function assertOrderMutable(
        ServiceOrder $order
    ): void {
        if ($order->status() === 'finalizada') {
            throw new InvalidArgumentException(
                'Estorne a OS finalizada antes de alterá-la.'
            );
        }

        if ($order->status() === 'cancelada') {
            throw new InvalidArgumentException(
                'Reabra a OS cancelada antes de alterá-la.'
            );
        }
    }

    private function validateReferences(
        ServiceOrderFormData $data
    ): void {
        $client = $this->clients->findByIdForUpdate(
            $data->clientId()
        );

        if ($client === null) {
            throw new InvalidArgumentException(
                'Cliente não encontrado.'
            );
        }

        foreach ($data->items() as $item) {
            if (
                $item->type() === 'servico'
                && (
                    $item->referenceId() === null
                    || $this->services->findByIdForUpdate(
                        $item->referenceId()
                    ) === null
                )
            ) {
                throw new InvalidArgumentException(
                    'Serviço da OS não encontrado.'
                );
            }

            if (
                $item->type() === 'produto'
                && (
                    $item->referenceId() === null
                    || $this->products->findByIdForUpdate(
                        $item->referenceId()
                    ) === null
                )
            ) {
                throw new InvalidArgumentException(
                    'Produto da OS não encontrado.'
                );
            }
        }
    }

    private function validateEmployees(
        ServiceOrderTeamData $team
    ): void {
        foreach (
            $team->members()
            as $member
        ) {
            $employee =
                $this->employees->findById(
                    $member->employeeId()
                );

            if ($employee === null) {
                throw new InvalidArgumentException(
                    'Funcionário da equipe não encontrado.'
                );
            }

            if (!$employee->isActive()) {
                throw new InvalidArgumentException(
                    'O funcionário '
                        . $employee->name()
                        . ' está inativo e não pode ser atribuído à OS.'
                );
            }
        }
    }

    private function validateStateRequirements(
        string $status,
        ?ServiceOrderTeamData $team,
        ?ServiceOrderScheduleData $schedule
    ): void {
        $requiresOperationalAssignment =
            in_array(
                $status,
                self::STATUS_REQUIRES_TEAM_AND_SCHEDULE,
                true
            )
            || $schedule !== null;

        if (
            $requiresOperationalAssignment
            && (
                $team === null
                || !$team->hasMembers()
                || $team->primaryEmployeeId() === null
                || $schedule === null
            )
        ) {
            throw new InvalidArgumentException(
                'Informe equipe com responsável principal e agendamento para esse status.'
            );
        }
    }

    private function validateOrderCanHaveStatus(
        ServiceOrder $order,
        string $status
    ): void {
        if (
            !in_array(
                $status,
                self::STATUS_REQUIRES_TEAM_AND_SCHEDULE,
                true
            )
        ) {
            return;
        }

        if (
            $order->scheduledStart() === null
            || $order->scheduledEnd() === null
        ) {
            throw new InvalidArgumentException(
                'Informe a data, a hora e a duração prevista do serviço antes de alterar o status.'
            );
        }

        $team = $this->teamFromOrder($order);

        $this->validateTeamForScheduledOrder(
            $team
        );

        $this->validateConflicts(
            $order->id(),
            $team,
            new ServiceOrderScheduleData(
                new DateTimeImmutable(
                    $order->scheduledStart()
                ),
                new DateTimeImmutable(
                    $order->scheduledEnd()
                )
            )
        );
    }

    private function validateConflictIfScheduled(
        ServiceOrder $order,
        ServiceOrderTeamData $team
    ): void {
        if (
            $order->scheduledStart() === null
            || $order->scheduledEnd() === null
        ) {
            return;
        }

        $this->validateTeamForScheduledOrder(
            $team
        );

        $this->validateConflicts(
            $order->id(),
            $team,
            new ServiceOrderScheduleData(
                new DateTimeImmutable(
                    $order->scheduledStart()
                ),
                new DateTimeImmutable(
                    $order->scheduledEnd()
                )
            )
        );
    }

    private function assertOrderCanReopen(
        ServiceOrder $order
    ): void {
        if (
            $order->status() === 'finalizada'
            && $this->hasExecutionLocks(
                $order->id()
            )
        ) {
            throw new InvalidArgumentException(
                'OS finalizada com execução, estoque, pagamento ou conta a receber não pode ser reaberta. Use estorno/correção financeira.'
            );
        }

        if (
            $order->status() === 'cancelada'
            && $order->budgetId() !== null
            && $this->orders
            ->hasOtherOperationalOrderForBudget(
                $order->budgetId(),
                $order->id()
            )
        ) {
            throw new InvalidArgumentException(
                'Não é possível reabrir: o orçamento já possui outra OS operacional.'
            );
        }
    }

    private function hasExecutionLocks(
        int $orderId
    ): bool {
        $queries = [
            'SELECT id
               FROM ordem_servico_finalizacoes
              WHERE ordem_servico_id = :id
                AND ativa = 1
              LIMIT 1
              FOR UPDATE',

            'SELECT id
               FROM estoque_movimentacoes
              WHERE ordem_servico_id = :id
              LIMIT 1
              FOR UPDATE',

            "SELECT id
               FROM ordem_servico_pagamentos
              WHERE ordem_servico_id = :id
                AND status = 'ativo'
              LIMIT 1
              FOR UPDATE",

            "SELECT id
               FROM contas_receber
              WHERE ordem_servico_id = :id
                AND status <> 'cancelada'
              LIMIT 1
              FOR UPDATE",
        ];

        foreach ($queries as $sql) {
            $statement = $this->connection->prepare(
                $sql
            );

            $statement->execute([
                'id' => $orderId,
            ]);

            if ($statement->fetch() !== false) {
                return true;
            }
        }

        return false;
    }

    private function createReplacementOrder(
        ServiceOrder $order
    ): ServiceOrder {
        $items = [];

        foreach (
            $this->orders->findItems(
                $order->id()
            ) as $item
        ) {
            $items[] = [
                'type' => $item->type(),
                'origin' => $item->origin(),
                'reference_id' => $item->referenceId(),
                'budget_item_id' => $item->budgetItemId(),
                'description' => $item->displayDescription(),
                'execution_location' => $item->executionLocation(),
                'unit' => $item->unit(),
                'quantity' => $item->quantity(),
                'unit_price' => $item->unitPrice(),
                'discount' => $item->discount(),
            ];
        }

        if ($items === []) {
            throw new InvalidArgumentException(
                'OS sem itens não pode gerar substituta.'
            );
        }

        $data = ServiceOrderFormData::fromArray([
            'client_id' => $order->clientId(),
            'budget_id' => $order->budgetId(),
            'status' => 'aguardando_agendamento',
            'priority' => $order->priority(),
            'equipment_type' => $order->equipmentType(),
            'equipment_brand' => $order->equipmentBrand(),
            'equipment_model' => $order->equipmentModel(),
            'equipment_capacity' => $order->equipmentCapacity(),

            'equipment_serial_number' =>
            $order->equipmentSerialNumber(),

            'equipment_environment' =>
            $order->equipmentEnvironment(),

            'equipment_location' =>
            $order->equipmentLocation(),

            'reported_problem' =>
            $order->reportedProblem(),

            'identified_problem' =>
            $order->identifiedProblem(),

            'diagnosis' => $order->diagnosis(),
            'solution' => $order->solution(),
            'recommendation' => $order->recommendation(),

            'internal_notes' =>
            $order->internalNotes(),

            'notes' => $order->notes(),
            'discount' => $order->discount(),
            'increase' => $order->increase(),
            'items' => $items,
        ]);

        return $this->orders->create(
            $data,
            null,
            null,
            null
        );
    }

    private function validateConflicts(
        ?int $orderId,
        ServiceOrderTeamData $team,
        ServiceOrderScheduleData $schedule
    ): void {
        $conflicts =
            $this->orders->employeeScheduleConflicts(
                $team->employeeIds(),
                $schedule->start(),
                $schedule->end(),
                $orderId
            );

        if ($conflicts === []) {
            return;
        }

        $messages = [];

        foreach ($conflicts as $conflict) {
            $name = trim((string) (
                $conflict['employee_name']
                ?? 'Funcionário'
            ));

            $orderNumber = trim((string) (
                $conflict['order_number']
                ?? ''
            ));

            $start = trim((string) (
                $conflict['scheduled_start']
                ?? ''
            ));

            $end = trim((string) (
                $conflict['scheduled_end']
                ?? ''
            ));

            $period = '';

            try {
                if ($start !== '' && $end !== '') {
                    $startDate = new DateTimeImmutable($start);
                    $endDate = new DateTimeImmutable($end);

                    $period = $startDate->format('d/m/Y H:i')
                        . (
                            $startDate->format('Y-m-d') === $endDate->format('Y-m-d')
                                ? ' às ' . $endDate->format('H:i')
                                : ' até ' . $endDate->format('d/m/Y H:i')
                        );
                }
            } catch (Throwable) {
                $period = '';
            }

            $message = $name !== ''
                ? $name
                : 'Funcionário';

            if ($period !== '') {
                $message .= ' está ocupado em ' . $period;
            } else {
                $message .= ' já possui atendimento no horário informado';
            }

            if ($orderNumber !== '') {
                $message .= ' na ' . $orderNumber;
            }

            $messages[] = $message;
        }

        $messages = array_values(
            array_unique($messages)
        );

        throw new InvalidArgumentException(
            'Conflito de horário: '
            . implode('; ', array_slice($messages, 0, 4))
            . '. A agenda bloqueia somente o intervalo em que o serviço será executado.'
        );
    }

    private function teamFromOrder(
        ServiceOrder $order
    ): ServiceOrderTeamData {
        $members = [];

        foreach (
            $this->orders->findTeamMembers(
                $order->id()
            ) as $member
        ) {
            $members[] =
                new ServiceOrderTeamMemberData(
                    $member->employeeId(),
                    $member->role(),
                    $member->primary()
                );
        }

        if ($members !== []) {
            return new ServiceOrderTeamData(
                $members
            );
        }

        return ServiceOrderTeamData::fromArray([
            'funcionario_principal_id' =>
            $order->primaryEmployeeId(),

            'funcionario_apoio_id' =>
            $order->supportEmployeeId(),
        ]);
    }

    private function validateTeamForScheduledOrder(
        ServiceOrderTeamData $team
    ): void {
        if (
            !$team->hasMembers()
            || $team->primaryEmployeeId() === null
        ) {
            throw new InvalidArgumentException(
                'Informe pelo menos um funcionário e um responsável principal para agendar.'
            );
        }

        $this->validateEmployees($team);
    }

    private function assertTransition(
        string $from,
        string $to
    ): void {
        if (
            !isset(self::TRANSITIONS[$from])
            || !in_array(
                $to,
                self::TRANSITIONS[$from],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Transição de status não permitida.'
            );
        }
    }

    private function cleanText(
        string $value,
        int $max,
        string $field
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || str_contains($value, "\0")
            || $value !== strip_tags($value)
            || mb_strlen($value) > $max
        ) {
            throw new InvalidArgumentException(
                'Informe ' . $field . ' válido.'
            );
        }

        return $value;
    }

    private function cleanOptionalText(
        string $value,
        int $max
    ): ?string {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            str_contains($value, "\0")
            || mb_strlen($value) > $max
        ) {
            throw new InvalidArgumentException(
                'Observação inválida.'
            );
        }

        return $value;
    }

    private function normalizeFinalizationQuantity(
        string $value
    ): string {
        $normalized = str_replace(
            ',',
            '.',
            trim($value)
        );

        if (
            preg_match(
                '/^\d+(?:\.\d{1,3})?$/',
                $normalized
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Informe uma quantidade válida.'
            );
        }

        $quantity = (float) $normalized;

        if (
            $quantity <= 0.0
            || $quantity > 999999999.999
        ) {
            throw new InvalidArgumentException(
                'Informe uma quantidade válida e maior que zero.'
            );
        }

        return number_format(
            $quantity,
            3,
            '.',
            ''
        );
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    private function transactional(
        callable $callback
    ): mixed {
        $ownsTransaction =
            !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $result = $callback();

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $result;
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
}
