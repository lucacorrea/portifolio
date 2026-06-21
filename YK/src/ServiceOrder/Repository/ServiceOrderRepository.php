<?php

declare(strict_types=1);

namespace App\ServiceOrder\Repository;

use App\ServiceOrder\Entity\ServiceOrder;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class ServiceOrderRepository
{
    private const BLOCKING_STATUSES = ['agendada', 'em_deslocamento', 'em_execucao'];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?ServiceOrder
    {
        $this->assertPositiveId($id);
        $orders = $this->selectOrders(['os.id = :id'], ['id' => $id], 'os.id DESC');
        return $orders[0] ?? null;
    }

    public function lockById(int $id): ?ServiceOrder
    {
        $this->assertPositiveId($id);
        $orders = $this->selectOrders(['os.id = :id'], ['id' => $id], 'os.id DESC', true);
        return $orders[0] ?? null;
    }

    /** @return ServiceOrder[] */
    public function findScheduledBetween(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return $this->selectOrders(
            ['os.agendado_inicio >= :start', 'os.agendado_inicio < :end'],
            ['start' => $this->formatDateTime($start), 'end' => $this->formatDateTime($end)],
            'os.agendado_inicio ASC, os.id ASC'
        );
    }

    public function hasEmployeeConflict(
        int $employeeId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $ignoreOrderId = null
    ): bool {
        return $this->employeeConflictNames([$employeeId], $start, $end, $ignoreOrderId) !== [];
    }

    /** @param int[] $employeeIds @return array<int,string> */
    public function employeeConflictNames(
        array $employeeIds,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $ignoreOrderId = null
    ): array {
        $employeeIds = array_values(array_unique(array_filter($employeeIds, static fn(int $id): bool => $id > 0)));
        if ($employeeIds === []) {
            return [];
        }

        $employeePlaceholders = [];
        $parameters = [
            'start' => $this->formatDateTime($start),
            'end' => $this->formatDateTime($end),
        ];

        foreach ($employeeIds as $index => $employeeId) {
            $placeholder = 'employee_' . $index;
            $employeePlaceholders[] = ':' . $placeholder;
            $parameters[$placeholder] = $employeeId;
        }

        $statusPlaceholders = [];
        foreach (self::BLOCKING_STATUSES as $index => $status) {
            $placeholder = 'status_' . $index;
            $statusPlaceholders[] = ':' . $placeholder;
            $parameters[$placeholder] = $status;
        }

        $sql = 'SELECT DISTINCT f.id, f.codigo, f.nome
                  FROM funcionarios f
                  JOIN ordens_servico os
                    ON os.funcionario_principal_id = f.id
                    OR os.funcionario_apoio_id = f.id
                 WHERE f.id IN (' . implode(', ', $employeePlaceholders) . ')
                   AND os.status IN (' . implode(', ', $statusPlaceholders) . ')
                   AND os.agendado_inicio IS NOT NULL
                   AND os.agendado_fim IS NOT NULL
                   AND :start < os.agendado_fim
                   AND :end > os.agendado_inicio';

        if ($ignoreOrderId !== null) {
            $this->assertPositiveId($ignoreOrderId);
            $sql .= ' AND os.id <> :ignore_order_id';
            $parameters['ignore_order_id'] = $ignoreOrderId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        $conflicts = [];
        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['id'];
            $conflicts[$id] = (string) $row['nome'];
        }

        return $conflicts;
    }

    public function updateTeam(int $orderId, int $primaryEmployeeId, int $supportEmployeeId): void
    {
        $this->assertPositiveId($orderId);
        $this->assertPositiveId($primaryEmployeeId);
        $this->assertPositiveId($supportEmployeeId);

        $statement = $this->connection->prepare(
            'UPDATE ordens_servico
                SET funcionario_principal_id = :primary_employee_id,
                    funcionario_apoio_id = :support_employee_id
              WHERE id = :order_id'
        );
        $statement->execute([
            'order_id' => $orderId,
            'primary_employee_id' => $primaryEmployeeId,
            'support_employee_id' => $supportEmployeeId,
        ]);
    }

    public function updateSchedule(int $orderId, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $this->assertPositiveId($orderId);

        $statement = $this->connection->prepare(
            'UPDATE ordens_servico
                SET agendado_inicio = :start,
                    agendado_fim = :end
              WHERE id = :order_id'
        );
        $statement->execute([
            'order_id' => $orderId,
            'start' => $this->formatDateTime($start),
            'end' => $this->formatDateTime($end),
        ]);
    }

    public function updateStatus(int $orderId, string $status): void
    {
        $this->assertPositiveId($orderId);

        $statement = $this->connection->prepare(
            'UPDATE ordens_servico
                SET status = :status
              WHERE id = :order_id'
        );
        $statement->execute([
            'order_id' => $orderId,
            'status' => $status,
        ]);
    }

    /** @param array<int,string> $where @return ServiceOrder[] */
    private function selectOrders(array $where, array $parameters, string $orderBy, bool $forUpdate = false): array
    {
        $sql = 'SELECT os.id, os.numero, os.cliente_id, c.nome AS cliente_nome, os.orcamento_id,
                       os.funcionario_principal_id, fp.codigo AS funcionario_principal_codigo, fp.nome AS funcionario_principal_nome,
                       os.funcionario_apoio_id, fa.codigo AS funcionario_apoio_codigo, fa.nome AS funcionario_apoio_nome,
                       os.agendado_inicio, os.agendado_fim, os.status, os.prioridade, os.observacoes,
                       os.criado_em, os.atualizado_em
                  FROM ordens_servico os
                  JOIN clientes c ON c.id = os.cliente_id
             LEFT JOIN funcionarios fp ON fp.id = os.funcionario_principal_id
             LEFT JOIN funcionarios fa ON fa.id = os.funcionario_apoio_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ' . $orderBy;

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return array_map(static fn(array $row): ServiceOrder => ServiceOrder::fromArray($row), $statement->fetchAll());
    }

    private function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de ordem de serviço inválido.');
        }
    }
}
