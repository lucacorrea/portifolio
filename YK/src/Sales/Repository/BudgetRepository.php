<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Sales\DTO\BudgetFormData;
use App\Sales\Entity\Budget;
use App\Sales\Entity\BudgetItem;
use InvalidArgumentException;
use PDO;
use Throwable;

final class BudgetRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return Budget[] */
    public function findAll(array $filters = []): array
    {
        [$where, $params] = $this->filters($filters);
        return $this->selectBudgets($where, $params, 'o.data_emissao DESC, o.id DESC');
    }

    public function findById(int $id): ?Budget
    {
        $this->assertPositiveId($id);
        $budgets = $this->selectBudgets(['o.id = :id'], ['id' => $id], 'o.id DESC');
        return $budgets[0] ?? null;
    }

    public function lockById(int $id): ?Budget
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id FROM orcamentos WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id]);

        if ($statement->fetch() === false) {
            return null;
        }

        return $this->findById($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function availableApprovedForServiceOrder(): array
    {
        $statement = $this->connection->query(
            "SELECT o.id, o.numero, o.cliente_id, c.nome AS cliente_nome, o.aprovado_em,
                    o.total, COUNT(i.id) AS itens_total,
                    GROUP_CONCAT(CASE WHEN i.tipo = 'servico' THEN i.descricao ELSE NULL END ORDER BY i.ordem SEPARATOR ', ') AS servicos_resumo
               FROM orcamentos o
               JOIN clientes c ON c.id = o.cliente_id
               JOIN orcamento_itens i ON i.orcamento_id = o.id
              WHERE o.status = 'aprovado'
                AND NOT EXISTS (
                    SELECT 1
                      FROM ordens_servico os
                     WHERE os.orcamento_id = o.id
                       AND os.status <> 'cancelada'
                )
                AND NOT EXISTS (
                    SELECT 1
                      FROM ordens_servico os
                     WHERE os.orcamento_id = o.id
                       AND os.status = 'cancelada'
                       AND os.orcamento_liberado = 0
                )
              GROUP BY o.id, o.numero, o.cliente_id, c.nome, o.aprovado_em, o.total
              ORDER BY o.aprovado_em DESC, o.id DESC"
        );

        return $statement->fetchAll();
    }

    /** @param int[] $budgetIds @return array<int,array{id:int,numero:string,status:string}> */
    public function operationalOrdersByBudget(array $budgetIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $budgetIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $statement = $this->connection->prepare(
            "SELECT orcamento_id, id, numero, status
               FROM ordens_servico
              WHERE orcamento_id IN (" . implode(', ', $placeholders) . ")
                AND (
                    status <> 'cancelada'
                    OR (status = 'cancelada' AND orcamento_liberado = 0)
                )
              ORDER BY id DESC"
        );
        $statement->execute($params);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $budgetId = (int) $row['orcamento_id'];
            $map[$budgetId] ??= ['id' => (int) $row['id'], 'numero' => (string) $row['numero'], 'status' => (string) $row['status']];
        }
        return $map;
    }

    /** @return BudgetItem[] */
    public function findItems(int $budgetId): array
    {
        $this->assertPositiveId($budgetId);
        $statement = $this->connection->prepare(
            'SELECT id, orcamento_id, tipo, referencia_id, descricao, unidade, quantidade,
                    valor_unitario, desconto, subtotal, ordem
               FROM orcamento_itens
              WHERE orcamento_id = :id
              ORDER BY ordem ASC, id ASC'
        );
        $statement->execute(['id' => $budgetId]);
        return array_map(static fn(array $row): BudgetItem => BudgetItem::fromArray($row), $statement->fetchAll());
    }

    /** @return array{draft:int,sent:int,waiting:int,approved:int,expired:int,approved_value:string} */
    public function summary(): array
    {
        $statement = $this->connection->query(
            "SELECT
                SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'aguardando_aprovacao' THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status IN ('enviado', 'aguardando_aprovacao') AND validade < CURRENT_DATE THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN status = 'aprovado' THEN total ELSE 0 END) AS approved_value
             FROM orcamentos"
        );
        $row = $statement->fetch() ?: [];
        return [
            'draft' => (int) ($row['draft'] ?? 0),
            'sent' => (int) ($row['sent'] ?? 0),
            'waiting' => (int) ($row['waiting'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
            'approved_value' => number_format((float) ($row['approved_value'] ?? 0), 2, '.', ''),
        ];
    }

    public function create(BudgetFormData $data): Budget
    {
        $this->connection->beginTransaction();
        try {
            $totals = $data->totals();
            $statement = $this->connection->prepare(
                'INSERT INTO orcamentos
                    (cliente_id, data_emissao, validade, status, observacoes,
                     subtotal_servicos, subtotal_produtos, subtotal_outros, desconto, acrescimo, total)
                 VALUES
                    (:client_id, :issue_date, :valid_until, :status, :notes,
                     :services_subtotal, :products_subtotal, :others_subtotal, :discount, :increase, :total)'
            );
            $this->bindForm($statement, $data, $totals);
            $statement->execute();

            $id = (int) $this->connection->lastInsertId();
            $this->assertPositiveId($id);
            $number = sprintf('ORC-%06d', $id);
            $this->connection->prepare('UPDATE orcamentos SET numero = :number WHERE id = :id')->execute(['number' => $number, 'id' => $id]);
            $this->insertItems($id, $data);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }

        $budget = $this->findById($id);
        if ($budget === null) throw new InvalidArgumentException('Orçamento não encontrado após cadastro.');
        return $budget;
    }

    public function update(int $id, BudgetFormData $data): void
    {
        $this->assertPositiveId($id);
        $this->connection->beginTransaction();
        try {
            $totals = $data->totals();
            $statement = $this->connection->prepare(
                'UPDATE orcamentos
                    SET cliente_id = :client_id,
                        data_emissao = :issue_date,
                        validade = :valid_until,
                        status = :status,
                        observacoes = :notes,
                        motivo_recusa = NULL,
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
            $this->connection->prepare('DELETE FROM orcamento_itens WHERE orcamento_id = :id')->execute(['id' => $id]);
            $this->insertItems($id, $data);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function approve(int $id): void
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            "UPDATE orcamentos
                SET status = 'aprovado', aprovado_em = COALESCE(aprovado_em, CURRENT_TIMESTAMP), recusado_em = NULL, motivo_recusa = NULL
              WHERE id = :id AND status <> 'aprovado'"
        );
        $statement->execute(['id' => $id]);
    }

    public function reject(int $id, ?string $reason = null): void
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            "UPDATE orcamentos
                SET status = 'recusado', recusado_em = COALESCE(recusado_em, CURRENT_TIMESTAMP), motivo_recusa = :reason
              WHERE id = :id AND status <> 'recusado'"
        );
        $statement->execute(['id' => $id, 'reason' => $reason]);
    }

    /** @return Budget[] */
    public function budgetsByClient(int $clientId): array
    {
        $this->assertPositiveId($clientId);
        return $this->selectBudgets(['o.cliente_id = :client_id'], ['client_id' => $clientId], 'o.data_emissao DESC, o.id DESC');
    }

    /** @return array{0:array<int,string>,1:array<string,mixed>} */
    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(o.numero LIKE :search_number OR c.codigo LIKE :search_client_code OR c.nome LIKE :search_client_name)';
            $like = '%' . $search . '%';
            $params += [
                'search_number' => $like,
                'search_client_code' => $like,
                'search_client_name' => $like,
            ];
        }
        foreach (['client_id' => 'o.cliente_id'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = (int) $value;
            }
        }
        if (trim((string) ($filters['date_from'] ?? '')) !== '') {
            $where[] = 'o.data_emissao >= :date_from';
            $params['date_from'] = trim((string) $filters['date_from']);
        }
        if (trim((string) ($filters['date_to'] ?? '')) !== '') {
            $where[] = 'o.data_emissao <= :date_to';
            $params['date_to'] = trim((string) $filters['date_to']);
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'vencido') {
            $where[] = "o.status IN ('enviado', 'aguardando_aprovacao') AND o.validade < CURRENT_DATE";
        } elseif ($status !== '') {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }
        return [$where, $params];
    }

    /** @param array<int,string> $where @return Budget[] */
    private function selectBudgets(array $where, array $params, string $orderBy, bool $forUpdate = false): array
    {
        $sql = 'SELECT o.id, o.numero, o.cliente_id, c.codigo AS cliente_codigo, c.nome AS cliente_nome,
                       c.documento AS cliente_documento,
                       o.data_emissao, o.validade, o.status, o.observacoes, o.motivo_recusa,
                       o.subtotal_servicos, o.subtotal_produtos, o.subtotal_outros,
                       o.desconto, o.acrescimo, o.total, o.aprovado_em, o.recusado_em,
                       o.criado_em, o.atualizado_em, COUNT(i.id) AS itens_total
                  FROM orcamentos o
                  JOIN clientes c ON c.id = o.cliente_id
             LEFT JOIN orcamento_itens i ON i.orcamento_id = o.id';
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' GROUP BY o.id, o.numero, o.cliente_id, c.codigo, c.nome, c.documento,
                         o.data_emissao, o.validade, o.status,
                         o.observacoes, o.motivo_recusa, o.subtotal_servicos, o.subtotal_produtos,
                         o.subtotal_outros, o.desconto, o.acrescimo, o.total, o.aprovado_em,
                         o.recusado_em, o.criado_em, o.atualizado_em
                  ORDER BY ' . $orderBy;
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return array_map(static fn(array $row): Budget => Budget::fromArray($row), $statement->fetchAll());
    }

    private function bindForm(\PDOStatement $statement, BudgetFormData $data, array $totals): void
    {
        $statement->bindValue('client_id', $data->clientId(), PDO::PARAM_INT);
        $statement->bindValue('issue_date', $data->issueDate());
        $statement->bindValue('valid_until', $data->validUntil());
        $statement->bindValue('status', $data->status());
        $statement->bindValue('notes', $data->notes());
        $statement->bindValue('services_subtotal', $totals['services']);
        $statement->bindValue('products_subtotal', $totals['products']);
        $statement->bindValue('others_subtotal', $totals['others']);
        $statement->bindValue('discount', $data->discount());
        $statement->bindValue('increase', $data->increase());
        $statement->bindValue('total', $totals['total']);
    }

    private function insertItems(int $budgetId, BudgetFormData $data): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO orcamento_itens
                (orcamento_id, tipo, referencia_id, descricao, unidade, quantidade, valor_unitario, desconto, subtotal, ordem)
             VALUES
                (:budget_id, :type, :reference_id, :description, :unit, :quantity, :unit_price, :discount, :subtotal, :order)'
        );
        foreach ($data->items() as $item) {
            $statement->execute([
                'budget_id' => $budgetId,
                'type' => $item->type(),
                'reference_id' => $item->referenceId(),
                'description' => $item->description(),
                'unit' => $item->unit(),
                'quantity' => $item->quantity(),
                'unit_price' => $item->unitPrice(),
                'discount' => $item->discount(),
                'subtotal' => $item->subtotal(),
                'order' => $item->order(),
            ]);
        }
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de orçamento inválido.');
    }
}
