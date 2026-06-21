<?php

declare(strict_types=1);

namespace App\ServiceOrder\Repository;

use App\ServiceOrder\DTO\ServiceOrderFormData;
use App\ServiceOrder\DTO\ServiceOrderItemData;
use App\ServiceOrder\Entity\ServiceOrder;
use App\ServiceOrder\Entity\ServiceOrderItem;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class ServiceOrderRepository
{
    private const BLOCKING_STATUSES = ['agendada', 'em_deslocamento', 'em_execucao'];

    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return ServiceOrder[] */
    public function findAll(array $filters = []): array
    {
        [$where, $params] = $this->filters($filters);
        return $this->selectOrders($where, $params, 'os.criado_em DESC, os.id DESC');
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $statement = $this->connection->query(
            "SELECT
                SUM(CASE WHEN status IN ('rascunho','aberta') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'aguardando_agendamento' THEN 1 ELSE 0 END) AS waiting_schedule,
                SUM(CASE WHEN status = 'agendada' THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN status IN ('em_deslocamento','em_execucao') THEN 1 ELSE 0 END) AS in_service,
                SUM(CASE WHEN status = 'aguardando_peca' THEN 1 ELSE 0 END) AS waiting_part,
                SUM(CASE WHEN status = 'finalizada' AND finalizada_em >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN 1 ELSE 0 END) AS finished_month,
                SUM(CASE WHEN prioridade = 'urgente' AND status NOT IN ('finalizada','cancelada') THEN 1 ELSE 0 END) AS urgent
             FROM ordens_servico"
        );
        $row = $statement->fetch() ?: [];
        return [
            'open_count' => (int) ($row['open_count'] ?? 0),
            'waiting_schedule' => (int) ($row['waiting_schedule'] ?? 0),
            'scheduled' => (int) ($row['scheduled'] ?? 0),
            'in_service' => (int) ($row['in_service'] ?? 0),
            'waiting_part' => (int) ($row['waiting_part'] ?? 0),
            'finished_month' => (int) ($row['finished_month'] ?? 0),
            'urgent' => (int) ($row['urgent'] ?? 0),
        ];
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

    /** @return ServiceOrderItem[] */
    public function findItems(int $orderId): array
    {
        $this->assertPositiveId($orderId);
        $statement = $this->connection->prepare(
            'SELECT id, ordem_servico_id, tipo, referencia_id, descricao, unidade, quantidade,
                    valor_unitario, desconto, subtotal, ordem
               FROM ordem_servico_itens
              WHERE ordem_servico_id = :id
              ORDER BY ordem ASC, id ASC'
        );
        $statement->execute(['id' => $orderId]);
        return array_map(static fn(array $row): ServiceOrderItem => ServiceOrderItem::fromArray($row), $statement->fetchAll());
    }

    /** @param int[] $orderIds @return array<int,ServiceOrderItem[]> */
    public function findItemsForOrders(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $statement = $this->connection->prepare(
            'SELECT id, ordem_servico_id, tipo, referencia_id, descricao, unidade, quantidade,
                    valor_unitario, desconto, subtotal, ordem
               FROM ordem_servico_itens
              WHERE ordem_servico_id IN (' . implode(', ', $placeholders) . ')
              ORDER BY ordem_servico_id ASC, ordem ASC, id ASC'
        );
        $statement->execute($params);

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $item = ServiceOrderItem::fromArray($row);
            $grouped[$item->orderId()][] = $item;
        }
        return $grouped;
    }

    public function create(ServiceOrderFormData $data, ?int $primaryEmployeeId, ?int $supportEmployeeId, ?DateTimeImmutable $start, ?DateTimeImmutable $end): ServiceOrder
    {
        $totals = $data->totals();
        $statement = $this->connection->prepare(
            'INSERT INTO ordens_servico
                (numero, cliente_id, orcamento_id, funcionario_principal_id, funcionario_apoio_id, agendado_inicio, agendado_fim,
                 status, prioridade, equipamento_tipo, equipamento_marca, equipamento_modelo, equipamento_capacidade,
                 equipamento_numero_serie, equipamento_ambiente, equipamento_local, problema_relatado, problema_identificado,
                 diagnostico, solucao, recomendacao, observacoes_internas, observacoes, subtotal_servicos,
                 subtotal_produtos, subtotal_outros, desconto, acrescimo, total)
             VALUES
                (NULL, :client_id, :budget_id, :primary_employee_id, :support_employee_id, :scheduled_start, :scheduled_end,
                 :status, :priority, :equipment_type, :equipment_brand, :equipment_model, :equipment_capacity,
                 :equipment_serial_number, :equipment_environment, :equipment_location, :reported_problem, :identified_problem,
                 :diagnosis, :solution, :recommendation, :internal_notes, :notes, :services_subtotal,
                 :products_subtotal, :others_subtotal, :discount, :increase, :total)'
        );
        $this->bindForm($statement, $data, $totals);
        $statement->bindValue('primary_employee_id', $primaryEmployeeId, $primaryEmployeeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue('support_employee_id', $supportEmployeeId, $supportEmployeeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue('scheduled_start', $start?->format('Y-m-d H:i:s'));
        $statement->bindValue('scheduled_end', $end?->format('Y-m-d H:i:s'));
        $statement->execute();

        $id = (int) $this->connection->lastInsertId();
        $this->assertPositiveId($id);
        $this->connection->prepare('UPDATE ordens_servico SET numero = :number WHERE id = :id')
            ->execute(['number' => sprintf('OS-%06d', $id), 'id' => $id]);
        $this->replaceItems($id, $data->items());

        $order = $this->findById($id);
        if ($order === null) throw new InvalidArgumentException('OS não encontrada após cadastro.');
        return $order;
    }

    public function updateCore(int $id, ServiceOrderFormData $data): void
    {
        $this->assertPositiveId($id);
        $totals = $data->totals();
        $statement = $this->connection->prepare(
            'UPDATE ordens_servico
                SET cliente_id = :client_id,
                    orcamento_id = :budget_id,
                    prioridade = :priority,
                    equipamento_tipo = :equipment_type,
                    equipamento_marca = :equipment_brand,
                    equipamento_modelo = :equipment_model,
                    equipamento_capacidade = :equipment_capacity,
                    equipamento_numero_serie = :equipment_serial_number,
                    equipamento_ambiente = :equipment_environment,
                    equipamento_local = :equipment_location,
                    problema_relatado = :reported_problem,
                    problema_identificado = :identified_problem,
                    diagnostico = :diagnosis,
                    solucao = :solution,
                    recomendacao = :recommendation,
                    observacoes_internas = :internal_notes,
                    observacoes = :notes,
                    subtotal_servicos = :services_subtotal,
                    subtotal_produtos = :products_subtotal,
                    subtotal_outros = :others_subtotal,
                    desconto = :discount,
                    acrescimo = :increase,
                    total = :total
              WHERE id = :id'
        );
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $this->bindForm($statement, $data, $totals);
        $statement->execute();
        $this->replaceItems($id, $data->items());
    }

    /** @param ServiceOrderItemData[] $items */
    public function replaceItems(int $orderId, array $items): void
    {
        $this->assertPositiveId($orderId);
        $this->connection->prepare('DELETE FROM ordem_servico_itens WHERE ordem_servico_id = :id')->execute(['id' => $orderId]);
        $statement = $this->connection->prepare(
            'INSERT INTO ordem_servico_itens
                (ordem_servico_id, tipo, referencia_id, descricao, unidade, quantidade, valor_unitario, desconto, subtotal, ordem)
             VALUES
                (:order_id, :type, :reference_id, :description, :unit, :quantity, :unit_price, :discount, :subtotal, :order_index)'
        );
        foreach ($items as $item) {
            $statement->execute([
                'order_id' => $orderId,
                'type' => $item->type(),
                'reference_id' => $item->referenceId(),
                'description' => $item->description(),
                'unit' => $item->unit(),
                'quantity' => $item->quantity(),
                'unit_price' => $item->unitPrice(),
                'discount' => $item->discount(),
                'subtotal' => $item->subtotal(),
                'order_index' => $item->order(),
            ]);
        }
    }

    /** @return ServiceOrder[] */
    public function findScheduledBetween(DateTimeImmutable $start, DateTimeImmutable $end, array $filters = []): array
    {
        [$extraWhere, $params] = $this->filters($filters);
        $where = array_merge(['os.agendado_inicio >= :start', 'os.agendado_inicio < :end'], $extraWhere);
        $params += ['start' => $this->formatDateTime($start), 'end' => $this->formatDateTime($end)];
        return $this->selectOrders($where, $params, 'os.agendado_inicio ASC, os.id ASC');
    }

    public function hasEmployeeConflict(int $employeeId, DateTimeImmutable $start, DateTimeImmutable $end, ?int $ignoreOrderId = null): bool
    {
        return $this->employeeConflictNames([$employeeId], $start, $end, $ignoreOrderId) !== [];
    }

    /** @param int[] $employeeIds @return array<int,string> */
    public function employeeConflictNames(array $employeeIds, DateTimeImmutable $start, DateTimeImmutable $end, ?int $ignoreOrderId = null): array
    {
        $employeeIds = array_values(array_unique(array_filter($employeeIds, static fn(int $id): bool => $id > 0)));
        if ($employeeIds === []) return [];

        $employeePlaceholders = [];
        $parameters = ['start' => $this->formatDateTime($start), 'end' => $this->formatDateTime($end)];
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

        $sql = 'SELECT DISTINCT f.id, f.nome
                  FROM funcionarios f
                  JOIN ordens_servico os
                    ON os.funcionario_principal_id = f.id OR os.funcionario_apoio_id = f.id
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
        $sql .= ' FOR UPDATE';

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        $conflicts = [];
        foreach ($statement->fetchAll() as $row) {
            $conflicts[(int) $row['id']] = (string) $row['nome'];
        }
        return $conflicts;
    }

    public function updateTeam(int $orderId, int $primaryEmployeeId, int $supportEmployeeId): void
    {
        $this->assertPositiveId($orderId);
        $statement = $this->connection->prepare(
            'UPDATE ordens_servico
                SET funcionario_principal_id = :primary_employee_id,
                    funcionario_apoio_id = :support_employee_id
              WHERE id = :order_id'
        );
        $statement->execute(['order_id' => $orderId, 'primary_employee_id' => $primaryEmployeeId, 'support_employee_id' => $supportEmployeeId]);
    }

    public function updateSchedule(int $orderId, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $this->assertPositiveId($orderId);
        $statement = $this->connection->prepare(
            'UPDATE ordens_servico SET agendado_inicio = :start, agendado_fim = :end WHERE id = :order_id'
        );
        $statement->execute(['order_id' => $orderId, 'start' => $this->formatDateTime($start), 'end' => $this->formatDateTime($end)]);
    }

    public function updateStatus(int $orderId, string $status): void
    {
        $this->assertPositiveId($orderId);
        $sets = ['status = :status'];
        $params = ['order_id' => $orderId, 'status' => $status];
        if ($status === 'finalizada') $sets[] = 'finalizada_em = COALESCE(finalizada_em, CURRENT_TIMESTAMP)';
        if ($status === 'cancelada') $sets[] = 'cancelada_em = COALESCE(cancelada_em, CURRENT_TIMESTAMP)';
        if (!in_array($status, ['finalizada', 'cancelada'], true)) {
            $sets[] = 'finalizada_em = NULL';
            $sets[] = 'cancelada_em = NULL';
        }
        $statement = $this->connection->prepare('UPDATE ordens_servico SET ' . implode(', ', $sets) . ' WHERE id = :order_id');
        $statement->execute($params);
    }

    /** @return array{0:array<int,string>,1:array<string,mixed>} */
    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(os.numero LIKE :search_number OR c.codigo LIKE :search_client_code OR c.nome LIKE :search_client_name OR os.equipamento_tipo LIKE :search_equipment_type OR os.equipamento_marca LIKE :search_equipment_brand OR os.equipamento_modelo LIKE :search_equipment_model OR os.equipamento_numero_serie LIKE :search_serial OR item_summary.servico_principal LIKE :search_main_service OR fp.nome LIKE :search_primary OR fa.nome LIKE :search_support)';
            $like = '%' . $search . '%';
            $params += [
                'search_number' => $like, 'search_client_code' => $like, 'search_client_name' => $like,
                'search_equipment_type' => $like, 'search_equipment_brand' => $like, 'search_equipment_model' => $like,
                'search_serial' => $like, 'search_main_service' => $like, 'search_primary' => $like, 'search_support' => $like,
            ];
        }
        foreach (['client_id' => 'os.cliente_id', 'primary_employee_id' => 'os.funcionario_principal_id', 'support_employee_id' => 'os.funcionario_apoio_id', 'status' => 'os.status', 'priority' => 'os.prioridade'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = in_array($key, ['client_id', 'primary_employee_id', 'support_employee_id'], true) ? (int) $value : $value;
            }
        }
        $employeeId = trim((string) ($filters['employee_id'] ?? ''));
        if ($employeeId !== '') {
            $where[] = '(os.funcionario_principal_id = :employee_id OR os.funcionario_apoio_id = :employee_id)';
            $params['employee_id'] = (int) $employeeId;
        }
        if (trim((string) ($filters['date_from'] ?? '')) !== '') {
            $where[] = 'DATE(os.agendado_inicio) >= :date_from';
            $params['date_from'] = trim((string) $filters['date_from']);
        }
        if (trim((string) ($filters['date_to'] ?? '')) !== '') {
            $where[] = 'DATE(os.agendado_inicio) <= :date_to';
            $params['date_to'] = trim((string) $filters['date_to']);
        }
        if (trim((string) ($filters['service'] ?? '')) !== '') {
            $where[] = 'item_summary.servicos LIKE :service_filter';
            $params['service_filter'] = '%' . trim((string) $filters['service']) . '%';
        }
        if (trim((string) ($filters['equipment'] ?? '')) !== '') {
            $where[] = '(os.equipamento_tipo LIKE :equipment_filter OR os.equipamento_marca LIKE :equipment_filter OR os.equipamento_modelo LIKE :equipment_filter)';
            $params['equipment_filter'] = '%' . trim((string) $filters['equipment']) . '%';
        }
        return [$where, $params];
    }

    /** @param array<int,string> $where @return ServiceOrder[] */
    private function selectOrders(array $where, array $parameters, string $orderBy, bool $forUpdate = false): array
    {
        $sql = 'SELECT os.id, os.numero, os.cliente_id, c.nome AS cliente_nome, c.endereco AS cliente_endereco,
                       c.numero AS cliente_numero, c.bairro AS cliente_bairro, c.cidade AS cliente_cidade, c.uf AS cliente_uf,
                       os.orcamento_id, os.funcionario_principal_id, fp.codigo AS funcionario_principal_codigo,
                       fp.nome AS funcionario_principal_nome, os.funcionario_apoio_id, fa.codigo AS funcionario_apoio_codigo,
                       fa.nome AS funcionario_apoio_nome, os.agendado_inicio, os.agendado_fim, os.status, os.prioridade,
                       os.equipamento_tipo, os.equipamento_marca, os.equipamento_modelo, os.equipamento_capacidade,
                       os.equipamento_numero_serie, os.equipamento_ambiente, os.equipamento_local,
                       os.problema_relatado, os.problema_identificado, os.diagnostico, os.solucao, os.recomendacao,
                       os.observacoes_internas, os.observacoes, os.subtotal_servicos, os.subtotal_produtos,
                       os.subtotal_outros, os.desconto, os.acrescimo, os.total, os.finalizada_em, os.cancelada_em,
                       os.criado_em, os.atualizado_em, COALESCE(item_summary.itens_total, 0) AS itens_total,
                       item_summary.servico_principal
                  FROM ordens_servico os
                  JOIN clientes c ON c.id = os.cliente_id
             LEFT JOIN funcionarios fp ON fp.id = os.funcionario_principal_id
             LEFT JOIN funcionarios fa ON fa.id = os.funcionario_apoio_id
             LEFT JOIN (
                    SELECT ordem_servico_id,
                           COUNT(*) AS itens_total,
                           MIN(CASE WHEN tipo = "servico" THEN descricao ELSE NULL END) AS servico_principal,
                           GROUP_CONCAT(CASE WHEN tipo = "servico" THEN descricao ELSE NULL END SEPARATOR ", ") AS servicos
                      FROM ordem_servico_itens
                     GROUP BY ordem_servico_id
             ) item_summary ON item_summary.ordem_servico_id = os.id';
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ' . $orderBy;
        if ($forUpdate) $sql .= ' FOR UPDATE';

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        return array_map(static fn(array $row): ServiceOrder => ServiceOrder::fromArray($row), $statement->fetchAll());
    }

    private function bindForm(\PDOStatement $statement, ServiceOrderFormData $data, array $totals): void
    {
        $statement->bindValue('client_id', $data->clientId(), PDO::PARAM_INT);
        $statement->bindValue('budget_id', $data->budgetId(), $data->budgetId() === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue('status', $data->status());
        $statement->bindValue('priority', $data->priority());
        $statement->bindValue('equipment_type', $data->equipmentType());
        $statement->bindValue('equipment_brand', $data->equipmentBrand());
        $statement->bindValue('equipment_model', $data->equipmentModel());
        $statement->bindValue('equipment_capacity', $data->equipmentCapacity());
        $statement->bindValue('equipment_serial_number', $data->equipmentSerialNumber());
        $statement->bindValue('equipment_environment', $data->equipmentEnvironment());
        $statement->bindValue('equipment_location', $data->equipmentLocation());
        $statement->bindValue('reported_problem', $data->reportedProblem());
        $statement->bindValue('identified_problem', $data->identifiedProblem());
        $statement->bindValue('diagnosis', $data->diagnosis());
        $statement->bindValue('solution', $data->solution());
        $statement->bindValue('recommendation', $data->recommendation());
        $statement->bindValue('internal_notes', $data->internalNotes());
        $statement->bindValue('notes', $data->notes());
        $statement->bindValue('services_subtotal', $totals['services']);
        $statement->bindValue('products_subtotal', $totals['products']);
        $statement->bindValue('others_subtotal', $totals['others']);
        $statement->bindValue('discount', $data->discount());
        $statement->bindValue('increase', $data->increase());
        $statement->bindValue('total', $totals['total']);
    }

    private function formatDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de ordem de serviço inválido.');
    }
}
