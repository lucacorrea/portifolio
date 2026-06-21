<?php

declare(strict_types=1);

namespace App\ServiceOrder\Service;

use App\ServiceOrder\DTO\ServiceOrderScheduleData;
use App\ServiceOrder\DTO\ServiceOrderTeamData;
use App\ServiceOrder\Entity\ServiceOrder;
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
    private const VALID_STATUSES = [
        'rascunho',
        'aberta',
        'aguardando_agendamento',
        'agendada',
        'em_deslocamento',
        'em_execucao',
        'aguardando_peca',
        'finalizada',
        'cancelada',
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly ServiceOrderRepository $orders,
        private readonly EmployeeRepository $employees
    ) {
    }

    public function assignTeam(int $orderId, ServiceOrderTeamData $data): void
    {
        $this->transactional(function () use ($orderId, $data): void {
            $order = $this->requireLockedOrder($orderId);
            $this->validateEmployees($data);
            $this->validateConflictIfScheduled($order, $data);
            $this->orders->updateTeam($orderId, $data->primaryEmployeeId(), $data->supportEmployeeId());
        });
    }

    public function scheduleOrder(int $orderId, ServiceOrderScheduleData $data): void
    {
        $this->transactional(function () use ($orderId, $data): void {
            $order = $this->requireLockedOrder($orderId);
            $team = $this->teamFromOrder($order);
            $this->validateTeamForScheduledOrder($team);
            $this->validateConflicts($orderId, $team, $data);
            $this->orders->updateSchedule($orderId, $data->start(), $data->end());
        });
    }

    public function assignTeamAndSchedule(
        int $orderId,
        ServiceOrderTeamData $team,
        ServiceOrderScheduleData $schedule
    ): void {
        $this->transactional(function () use ($orderId, $team, $schedule): void {
            $this->requireLockedOrder($orderId);
            $this->validateEmployees($team);
            $this->validateConflicts($orderId, $team, $schedule);
            $this->orders->updateTeam($orderId, $team->primaryEmployeeId(), $team->supportEmployeeId());
            $this->orders->updateSchedule($orderId, $schedule->start(), $schedule->end());
        });
    }

    public function changeStatus(int $orderId, string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Status inválido para ordem de serviço.');
        }

        $this->transactional(function () use ($orderId, $status): void {
            $order = $this->requireLockedOrder($orderId);

            if (in_array($status, self::STATUS_REQUIRES_TEAM_AND_SCHEDULE, true)) {
                $team = $this->teamFromOrder($order);
                if ($order->scheduledStart() === null || $order->scheduledEnd() === null) {
                    throw new InvalidArgumentException('Informe início e fim do agendamento antes de alterar o status.');
                }

                $this->validateTeamForScheduledOrder($team);
                $this->validateConflicts(
                    $orderId,
                    $team,
                    new ServiceOrderScheduleData(new DateTimeImmutable($order->scheduledStart()), new DateTimeImmutable($order->scheduledEnd()))
                );
            }

            $this->orders->updateStatus($orderId, $status);
        });
    }

    /** @return ServiceOrder[] */
    public function calendarBetween(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('Período inválido para a agenda.');
        }

        return $this->orders->findScheduledBetween($start, $end);
    }

    /** @return array<string,ServiceOrder[]> */
    public function weekSchedule(DateTimeImmutable $weekStart): array
    {
        $monday = $weekStart->modify('monday this week')->setTime(0, 0);
        $nextMonday = $monday->add(new DateInterval('P7D'));
        $orders = $this->calendarBetween($monday, $nextMonday);

        $grouped = [];
        foreach ($orders as $order) {
            $day = $order->scheduledStart() === null
                ? $monday->format('Y-m-d')
                : (new DateTimeImmutable($order->scheduledStart()))->format('Y-m-d');
            $grouped[$day][] = $order;
        }

        return $grouped;
    }

    private function requireLockedOrder(int $orderId): ServiceOrder
    {
        $order = $this->orders->lockById($orderId);
        if ($order === null) {
            throw new InvalidArgumentException('Ordem de serviço não encontrada.');
        }

        return $order;
    }

    private function validateEmployees(ServiceOrderTeamData $team): void
    {
        if ($this->employees->findById($team->primaryEmployeeId()) === null) {
            throw new InvalidArgumentException('Funcionário principal não encontrado.');
        }

        if ($this->employees->findById($team->supportEmployeeId()) === null) {
            throw new InvalidArgumentException('Funcionário de apoio não encontrado.');
        }
    }

    private function validateConflictIfScheduled(ServiceOrder $order, ServiceOrderTeamData $team): void
    {
        if ($order->scheduledStart() === null || $order->scheduledEnd() === null) {
            return;
        }

        $this->validateConflicts(
            $order->id(),
            $team,
            new ServiceOrderScheduleData(new DateTimeImmutable($order->scheduledStart()), new DateTimeImmutable($order->scheduledEnd()))
        );
    }

    private function validateConflicts(int $orderId, ServiceOrderTeamData $team, ServiceOrderScheduleData $schedule): void
    {
        $conflicts = $this->orders->employeeConflictNames(
            [$team->primaryEmployeeId(), $team->supportEmployeeId()],
            $schedule->start(),
            $schedule->end(),
            $orderId
        );

        if ($conflicts === []) {
            return;
        }

        throw new InvalidArgumentException(
            count($conflicts) === 1
                ? 'O funcionário ' . reset($conflicts) . ' já possui atendimento nesse período.'
                : 'Os funcionários ' . implode(', ', $conflicts) . ' já possuem atendimento nesse período.'
        );
    }

    private function teamFromOrder(ServiceOrder $order): ServiceOrderTeamData
    {
        return new ServiceOrderTeamData((int) $order->primaryEmployeeId(), (int) $order->supportEmployeeId());
    }

    private function validateTeamForScheduledOrder(ServiceOrderTeamData $team): void
    {
        $this->validateEmployees($team);
    }

    private function transactional(callable $callback): void
    {
        $this->connection->beginTransaction();

        try {
            $callback();
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
