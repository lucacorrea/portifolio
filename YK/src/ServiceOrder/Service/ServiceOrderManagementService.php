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
    private const STATUS_REQUIRES_TEAM_AND_SCHEDULE = ['agendada', 'em_deslocamento', 'em_execucao'];
    private const TRANSITIONS = [
        'rascunho' => ['aberta', 'aguardando_agendamento', 'cancelada'],
        'aberta' => ['aguardando_agendamento', 'agendada', 'cancelada'],
        'aguardando_agendamento' => ['agendada', 'cancelada'],
        'agendada' => ['em_deslocamento', 'em_execucao', 'aguardando_peca', 'cancelada'],
        'em_deslocamento' => ['em_execucao', 'aguardando_peca', 'cancelada'],
        'em_execucao' => ['aguardando_peca', 'finalizada', 'cancelada'],
        'aguardando_peca' => ['agendada', 'em_execucao', 'cancelada'],
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
    ) {
    }

    /** @return ServiceOrder[] */
    public function listOrders(array $filters = []): array { return $this->orders->findAll($filters); }
    public function orderSummary(): array { return $this->orders->summary(); }

    /** @return array<int,array<string,mixed>> */
    public function availableApprovedBudgets(): array
    {
        if ($this->budgets === null) return [];
        return $this->budgets->availableApprovedForServiceOrder();
    }

    public function getOrder(int $id): ServiceOrder
    {
        $order = $this->orders->findById($id);
        if ($order === null) throw new InvalidArgumentException('Ordem de serviço não encontrada.');
        return $order;
    }

    /** @return ServiceOrderItem[] */
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

    /** @param ServiceOrder[] $orders @return array<int,array> */
    public function teamMembersForOrders(array $orders): array
    {
        return $this->orders->findTeamMembersForOrders(
            array_map(static fn(ServiceOrder $order): int => $order->id(), $orders)
        );
    }

    public function createOrder(ServiceOrderFormData $data, ?ServiceOrderTeamData $team, ?ServiceOrderScheduleData $schedule): ServiceOrder
    {
        return $this->transactional(function () use ($data, $team, $schedule): ServiceOrder {
            $this->validateReferences($data);
            $this->validateStateRequirements($data->status(), $team, $schedule);
            if ($team !== null && $team->hasMembers()) $this->validateEmployees($team);

            return $this->orders->create(
                $data,
                $team,
                $schedule?->start(),
                $schedule?->end()
            );
        });
    }

    public function createOrderFromApprovedBudget(int $budgetId, ?ServiceOrderTeamData $team, ?ServiceOrderScheduleData $schedule, bool $draft): ServiceOrder
    {
        if ($this->budgets === null) {
            throw new InvalidArgumentException('Integração de orçamento indisponível.');
        }

        return $this->transactional(function () use ($budgetId, $team, $schedule, $draft): ServiceOrder {
            $budget = $this->budgets->lockById($budgetId);
            if ($budget === null) {
                throw new InvalidArgumentException('Orçamento não encontrado.');
            }
            if ($budget->status() !== 'aprovado') {
                throw new InvalidArgumentException('Somente orçamento aprovado pode gerar OS.');
            }
            if ($this->orders->hasOperationalOrderForBudget($budgetId)) {
                throw new InvalidArgumentException('Este orçamento já possui uma OS operacional vinculada.');
            }

            $budgetItems = $this->budgets->findItems($budgetId);
            if ($budgetItems === []) {
                throw new InvalidArgumentException('Orçamento aprovado sem itens não pode gerar OS.');
            }

            $status = $draft ? 'rascunho' : ($schedule === null ? 'aguardando_agendamento' : 'agendada');
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

            $this->validateStateRequirements($data->status(), $team, $schedule);
            if ($team !== null && $team->hasMembers()) {
                $this->validateEmployees($team);
            }

            return $this->orders->create(
                $data,
                $team,
                $schedule?->start(),
                $schedule?->end()
            );
        });
    }

    public function approveBudgetAndCreateOrder(int $budgetId): ServiceOrder
    {
        if ($this->budgets === null) {
            throw new InvalidArgumentException('Integração de orçamento indisponível.');
        }

        return $this->transactional(function () use ($budgetId): ServiceOrder {
            $budget = $this->budgets->lockById($budgetId);
            if ($budget === null) {
                throw new InvalidArgumentException('Orçamento não encontrado.');
            }
            if ($budget->status() === 'recusado') {
                throw new InvalidArgumentException('Orçamento recusado não pode ser aprovado.');
            }
            if ($this->orders->hasOperationalOrderForBudget($budgetId)) {
                throw new InvalidArgumentException('Este orçamento já possui uma OS operacional vinculada.');
            }

            $budgetItems = $this->budgets->findItems($budgetId);
            if ($budgetItems === []) {
                throw new InvalidArgumentException('Não é possível aprovar orçamento sem itens.');
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

            $order = $this->orders->create($data, null, null, null);
            $this->connection->prepare(
                'UPDATE ordens_servico
                    SET valor_aprovado_orcamento = :approved_value
                  WHERE id = :order_id'
            )->execute([
                'approved_value' => $budget->total(),
                'order_id' => $order->id(),
            ]);

            return $this->getOrder($order->id());
        });
    }

    public function updateOrder(
        int $id,
        ServiceOrderFormData $data,
        ?ServiceOrderTeamData $team = null,
        ?ServiceOrderScheduleData $schedule = null,
        bool $teamSubmitted = false,
        bool $scheduleSubmitted = false
    ): void
    {
        $this->transactional(function () use ($id, $data, $team, $schedule, $teamSubmitted, $scheduleSubmitted): void {
            $order = $this->requireLockedOrder($id);
            $this->assertOrderMutable($order);
            $this->validateReferences($data);

            $effectiveTeam = null;
            if ($teamSubmitted) {
                $effectiveTeam = $team ?? new ServiceOrderTeamData([]);
            } elseif ($scheduleSubmitted) {
                $effectiveTeam = $this->teamFromOrder($order);
            }

            if ($teamSubmitted && $effectiveTeam !== null && $effectiveTeam->hasMembers()) {
                $this->validateEmployees($effectiveTeam);
            }

            if ($scheduleSubmitted && $schedule !== null && $effectiveTeam !== null) {
                $this->validateTeamForScheduledOrder($effectiveTeam);
                $this->validateConflicts($id, $effectiveTeam, $schedule);
            } elseif ($teamSubmitted && $effectiveTeam !== null && $order->scheduledStart() !== null && $order->scheduledEnd() !== null) {
                $this->validateTeamForScheduledOrder($effectiveTeam);
                $this->validateConflicts(
                    $id,
                    $effectiveTeam,
                    new ServiceOrderScheduleData(new DateTimeImmutable($order->scheduledStart()), new DateTimeImmutable($order->scheduledEnd()))
                );
            }

            $this->orders->updateCore($id, $data);
            if ($teamSubmitted && $effectiveTeam !== null) {
                $this->orders->replaceTeam($id, $effectiveTeam);
            }
            if ($scheduleSubmitted && $schedule !== null) {
                $this->orders->updateSchedule($id, $schedule->start(), $schedule->end());
                if (in_array($order->status(), ['rascunho', 'aberta', 'aguardando_agendamento'], true)) {
                    $this->orders->updateStatus($id, 'agendada');
                }
            }
        });
    }

    public function assignTeam(int $orderId, ServiceOrderTeamData $data): void
    {
        $this->transactional(function () use ($orderId, $data): void {
            $order = $this->requireLockedOrder($orderId);
            $this->assertOrderMutable($order);
            if ($data->hasMembers()) $this->validateEmployees($data);
            $this->validateConflictIfScheduled($order, $data);
            $this->orders->replaceTeam($orderId, $data);
        });
    }

    public function reassignTeam(int $orderId, ServiceOrderTeamData $team): void { $this->assignTeam($orderId, $team); }

    public function scheduleOrder(int $orderId, ServiceOrderScheduleData $data): void
    {
        $this->reschedule($orderId, $data);
    }

    public function reschedule(int $orderId, ServiceOrderScheduleData $schedule): void
    {
        $this->transactional(function () use ($orderId, $schedule): void {
            $order = $this->requireLockedOrder($orderId);
            $this->assertOrderMutable($order);
            $team = $this->teamFromOrder($order);
            $this->validateTeamForScheduledOrder($team);
            $this->validateConflicts($orderId, $team, $schedule);
            $this->orders->updateSchedule($orderId, $schedule->start(), $schedule->end());
            if (in_array($order->status(), ['rascunho', 'aberta', 'aguardando_agendamento'], true)) {
                $this->orders->updateStatus($orderId, 'agendada');
            }
        });
    }

    public function assignTeamAndSchedule(int $orderId, ServiceOrderTeamData $team, ServiceOrderScheduleData $schedule): void
    {
        $this->transactional(function () use ($orderId, $team, $schedule): void {
            $order = $this->requireLockedOrder($orderId);
            $this->assertOrderMutable($order);
            $this->validateTeamForScheduledOrder($team);
            $this->validateConflicts($orderId, $team, $schedule);
            $this->orders->replaceTeam($orderId, $team);
            $this->orders->updateSchedule($orderId, $schedule->start(), $schedule->end());
            $this->orders->updateStatus($orderId, 'agendada');
        });
    }

    public function changeStatus(int $orderId, string $status): void
    {
        $this->transactional(function () use ($orderId, $status): void {
            $order = $this->requireLockedOrder($orderId);
            $this->assertTransition($order->status(), $status);
            $this->validateOrderCanHaveStatus($order, $status);
            $this->orders->updateStatus($orderId, $status);
        });
    }

    public function finalize(int $orderId): void { $this->changeStatus($orderId, 'finalizada'); }
    public function cancel(int $orderId): void { $this->changeStatus($orderId, 'cancelada'); }

    public function cancelWithDetails(int $orderId, string $option, string $reason, ?string $notes, int $userId): void
    {
        $this->transactional(function () use ($orderId, $option, $reason, $notes, $userId): void {
            $order = $this->requireLockedOrder($orderId);
            if (in_array($order->status(), ['finalizada', 'cancelada'], true)) {
                throw new InvalidArgumentException('OS finalizada ou cancelada não pode ser cancelada novamente.');
            }
            if (!in_array($option, ['definitivo', 'liberar_orcamento', 'criar_substituta'], true)) {
                throw new InvalidArgumentException('Destino do orçamento inválido.');
            }
            $reason = $this->cleanText($reason, 150, 'motivo');
            $notes = $notes === null ? null : $this->cleanOptionalText($notes, 1000);
            $releaseBudget = in_array($option, ['liberar_orcamento', 'criar_substituta'], true) ? 1 : 0;

            $statement = $this->connection->prepare(
                'INSERT INTO ordem_servico_cancelamentos
                    (ordem_servico_id, opcao, motivo, observacao, orcamento_liberado, cancelado_por)
                 VALUES
                    (:order_id, :option, :reason, :notes, :release_budget, :user_id)'
            );
            $statement->execute([
                'order_id' => $orderId,
                'option' => $option,
                'reason' => $reason,
                'notes' => $notes,
                'release_budget' => $releaseBudget,
                'user_id' => $userId,
            ]);
            $cancellationId = (int) $this->connection->lastInsertId();

            $this->connection->prepare(
                "UPDATE ordens_servico
                    SET status = 'cancelada',
                        cancelada_em = COALESCE(cancelada_em, CURRENT_TIMESTAMP),
                        orcamento_liberado = :release_budget
                  WHERE id = :order_id"
            )->execute(['order_id' => $orderId, 'release_budget' => $releaseBudget]);
            $this->orders->syncOperationalBudgetKey($orderId);

            if ($option === 'criar_substituta') {
                $replacement = $this->createReplacementOrder($order);
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
        });
    }

    public function reopen(int $orderId): void
    {
        $this->transactional(function () use ($orderId): void {
            $order = $this->requireLockedOrder($orderId);
            if (!in_array($order->status(), ['finalizada', 'cancelada'], true)) {
                throw new InvalidArgumentException('Apenas OS finalizada ou cancelada pode ser reaberta.');
            }
            $this->assertOrderCanReopen($order);
            $this->orders->updateStatus($orderId, 'aberta');
            if ($order->status() === 'cancelada' && $order->budgetId() !== null) {
                $this->orders->markBudgetReleased($orderId, false);
            }
        });
    }

    /** @return ServiceOrder[] */
    public function calendarBetween(DateTimeImmutable $start, DateTimeImmutable $end, array $filters = []): array
    {
        if ($end <= $start) throw new InvalidArgumentException('Período inválido para a agenda.');
        return $this->orders->findScheduledBetween($start, $end, $filters);
    }

    /** @return array<string,ServiceOrder[]> */
    public function weekSchedule(DateTimeImmutable $weekStart, array $filters = []): array
    {
        $monday = $weekStart->modify('monday this week')->setTime(0, 0);
        $nextMonday = $monday->add(new DateInterval('P7D'));
        $orders = $this->calendarBetween($monday, $nextMonday, $filters);
        $grouped = [];
        foreach ($orders as $order) {
            $day = $order->scheduledStart() === null ? $monday->format('Y-m-d') : (new DateTimeImmutable($order->scheduledStart()))->format('Y-m-d');
            $grouped[$day][] = $order;
        }
        return $grouped;
    }

    private function requireLockedOrder(int $orderId): ServiceOrder
    {
        $order = $this->orders->lockById($orderId);
        if ($order === null) throw new InvalidArgumentException('Ordem de serviço não encontrada.');
        return $order;
    }

    private function assertOrderMutable(ServiceOrder $order): void
    {
        if ($order->status() === 'finalizada') {
            throw new InvalidArgumentException('Estorne a OS finalizada antes de alterá-la.');
        }
        if ($order->status() === 'cancelada') {
            throw new InvalidArgumentException('Reabra a OS cancelada antes de alterá-la.');
        }
    }

    private function validateReferences(ServiceOrderFormData $data): void
    {
        $client = $this->clients->findByIdForUpdate($data->clientId());
        if ($client === null) throw new InvalidArgumentException('Cliente não encontrado.');
        foreach ($data->items() as $item) {
            if ($item->type() === 'servico' && ($item->referenceId() === null || $this->services->findByIdForUpdate($item->referenceId()) === null)) {
                throw new InvalidArgumentException('Serviço da OS não encontrado.');
            }
            if ($item->type() === 'produto' && ($item->referenceId() === null || $this->products->findByIdForUpdate($item->referenceId()) === null)) {
                throw new InvalidArgumentException('Produto da OS não encontrado.');
            }
        }
    }

    private function validateEmployees(ServiceOrderTeamData $team): void
    {
        foreach ($team->members() as $member) {
            if ($this->employees->findById($member->employeeId()) === null) {
                throw new InvalidArgumentException('Funcionário da equipe não encontrado.');
            }
        }
    }

    private function validateStateRequirements(string $status, ?ServiceOrderTeamData $team, ?ServiceOrderScheduleData $schedule): void
    {
        $requiresOperationalAssignment = in_array($status, self::STATUS_REQUIRES_TEAM_AND_SCHEDULE, true) || $schedule !== null;
        if ($requiresOperationalAssignment && ($team === null || !$team->hasMembers() || $team->primaryEmployeeId() === null || $schedule === null)) {
            throw new InvalidArgumentException('Informe equipe com responsável principal e agendamento para esse status.');
        }
    }

    private function validateOrderCanHaveStatus(ServiceOrder $order, string $status): void
    {
        if (!in_array($status, self::STATUS_REQUIRES_TEAM_AND_SCHEDULE, true)) return;
        if ($order->scheduledStart() === null || $order->scheduledEnd() === null) {
            throw new InvalidArgumentException('Informe início e fim do agendamento antes de alterar o status.');
        }
        $team = $this->teamFromOrder($order);
        $this->validateTeamForScheduledOrder($team);
        $this->validateConflicts($order->id(), $team, new ServiceOrderScheduleData(new DateTimeImmutable($order->scheduledStart()), new DateTimeImmutable($order->scheduledEnd())));
    }

    private function validateConflictIfScheduled(ServiceOrder $order, ServiceOrderTeamData $team): void
    {
        if ($order->scheduledStart() === null || $order->scheduledEnd() === null) return;
        $this->validateTeamForScheduledOrder($team);
        $this->validateConflicts($order->id(), $team, new ServiceOrderScheduleData(new DateTimeImmutable($order->scheduledStart()), new DateTimeImmutable($order->scheduledEnd())));
    }

    private function assertOrderCanReopen(ServiceOrder $order): void
    {
        if ($order->status() === 'finalizada' && $this->hasExecutionLocks($order->id())) {
            throw new InvalidArgumentException('OS finalizada com execução, estoque, pagamento ou conta a receber não pode ser reaberta. Use estorno/correção financeira.');
        }
        if ($order->status() === 'cancelada' && $order->budgetId() !== null && $this->orders->hasOtherOperationalOrderForBudget($order->budgetId(), $order->id())) {
            throw new InvalidArgumentException('Não é possível reabrir: o orçamento já possui outra OS operacional.');
        }
    }

    private function hasExecutionLocks(int $orderId): bool
    {
        foreach ([
            'SELECT id FROM ordem_servico_finalizacoes WHERE ordem_servico_id = :id AND ativa = 1 LIMIT 1 FOR UPDATE',
            'SELECT id FROM estoque_movimentacoes WHERE ordem_servico_id = :id LIMIT 1 FOR UPDATE',
            "SELECT id FROM ordem_servico_pagamentos WHERE ordem_servico_id = :id AND status = 'ativo' LIMIT 1 FOR UPDATE",
            "SELECT id FROM contas_receber WHERE ordem_servico_id = :id AND status <> 'cancelada' LIMIT 1 FOR UPDATE",
        ] as $sql) {
            $statement = $this->connection->prepare($sql);
            $statement->execute(['id' => $orderId]);
            if ($statement->fetch() !== false) return true;
        }
        return false;
    }

    private function createReplacementOrder(ServiceOrder $order): ServiceOrder
    {
        $items = [];
        foreach ($this->orders->findItems($order->id()) as $item) {
            $items[] = [
                'type' => $item->type(),
                'origin' => $item->origin(),
                'reference_id' => $item->referenceId(),
                'budget_item_id' => $item->budgetItemId(),
                'description' => $item->description(),
                'unit' => $item->unit(),
                'quantity' => $item->quantity(),
                'unit_price' => $item->unitPrice(),
                'discount' => $item->discount(),
            ];
        }
        if ($items === []) {
            throw new InvalidArgumentException('OS sem itens não pode gerar substituta.');
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
            'equipment_serial_number' => $order->equipmentSerialNumber(),
            'equipment_environment' => $order->equipmentEnvironment(),
            'equipment_location' => $order->equipmentLocation(),
            'reported_problem' => $order->reportedProblem(),
            'identified_problem' => $order->identifiedProblem(),
            'diagnosis' => $order->diagnosis(),
            'solution' => $order->solution(),
            'recommendation' => $order->recommendation(),
            'internal_notes' => $order->internalNotes(),
            'notes' => $order->notes(),
            'discount' => $order->discount(),
            'increase' => $order->increase(),
            'items' => $items,
        ]);

        return $this->orders->create($data, null, null, null);
    }

    private function validateConflicts(?int $orderId, ServiceOrderTeamData $team, ServiceOrderScheduleData $schedule): void
    {
        $conflicts = $this->orders->employeeConflictNames($team->employeeIds(), $schedule->start(), $schedule->end(), $orderId);
        if ($conflicts === []) return;
        throw new InvalidArgumentException(
            count($conflicts) === 1
                ? 'O funcionário ' . reset($conflicts) . ' já possui atendimento nesse período.'
                : 'Os funcionários ' . implode(', ', $conflicts) . ' já possuem atendimento nesse período.'
        );
    }

    private function teamFromOrder(ServiceOrder $order): ServiceOrderTeamData
    {
        $members = [];
        foreach ($this->orders->findTeamMembers($order->id()) as $member) {
            $members[] = new ServiceOrderTeamMemberData($member->employeeId(), $member->role(), $member->primary());
        }

        if ($members !== []) {
            return new ServiceOrderTeamData($members);
        }

        return ServiceOrderTeamData::fromArray([
            'funcionario_principal_id' => $order->primaryEmployeeId(),
            'funcionario_apoio_id' => $order->supportEmployeeId(),
        ]);
    }

    private function validateTeamForScheduledOrder(ServiceOrderTeamData $team): void
    {
        if (!$team->hasMembers() || $team->primaryEmployeeId() === null) {
            throw new InvalidArgumentException('Informe pelo menos um funcionário e um responsável principal para agendar.');
        }
        $this->validateEmployees($team);
    }

    private function assertTransition(string $from, string $to): void
    {
        if (!isset(self::TRANSITIONS[$from]) || !in_array($to, self::TRANSITIONS[$from], true)) {
            throw new InvalidArgumentException('Transição de status não permitida.');
        }
    }

    private function cleanText(string $value, int $max, string $field): string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, "\0") || $value !== strip_tags($value) || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Informe ' . $field . ' válido.');
        }
        return $value;
    }

    private function cleanOptionalText(string $value, int $max): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (str_contains($value, "\0") || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Observação inválida.');
        }
        return $value;
    }

    /** @template T @param callable():T $callback @return T */
    private function transactional(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }
}
