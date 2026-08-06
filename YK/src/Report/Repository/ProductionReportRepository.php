<?php

declare(strict_types=1);

namespace App\Report\Repository;

use InvalidArgumentException;
use PDO;
use Throwable;

final class ProductionReportRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string,mixed>|null */
    public function activeGoal(string $competence): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, competencia, versao, valor_meta, percentual_comissao,
                    criada_por, criada_em
               FROM metas_comissao_mensais
              WHERE competencia = :competence AND ativa = 1
              LIMIT 1'
        );
        $statement->execute(['competence' => $competence]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function saveGoal(
        string $competence,
        string $goalAmount,
        string $commissionPercentage,
        int $userId
    ): void {
        $this->connection->beginTransaction();
        try {
            $versionStatement = $this->connection->prepare(
                'SELECT COALESCE(MAX(versao), 0) AS ultima_versao
                   FROM metas_comissao_mensais
                  WHERE competencia = :competence
                  FOR UPDATE'
            );
            $versionStatement->execute(['competence' => $competence]);
            $version = ((int) ($versionStatement->fetchColumn() ?: 0)) + 1;

            $this->connection->prepare(
                'UPDATE metas_comissao_mensais
                    SET ativa = 0,
                        desativada_por = :user_id,
                        desativada_em = CURRENT_TIMESTAMP
                  WHERE competencia = :competence AND ativa = 1'
            )->execute([
                'competence' => $competence,
                'user_id' => $userId,
            ]);

            $this->connection->prepare(
                'INSERT INTO metas_comissao_mensais
                    (competencia, versao, valor_meta, percentual_comissao, ativa, criada_por)
                 VALUES
                    (:competence, :version, :goal_amount, :commission_percentage, 1, :user_id)'
            )->execute([
                'competence' => $competence,
                'version' => $version,
                'goal_amount' => $goalAmount,
                'commission_percentage' => $commissionPercentage,
                'user_id' => $userId,
            ]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function summary(string $start, string $endExclusive, bool $includeFinancial = true): array
    {
        $financialColumns = $includeFinancial
            ? 'COALESCE(SUM(fin.total_executado), 0.00) AS company_total,
               COALESCE(SUM(fin.subtotal_servicos), 0.00) AS service_total,
               COALESCE(SUM(fin.subtotal_produtos), 0.00) AS product_total,
               COALESCE(SUM(fin.subtotal_outros), 0.00) AS other_total,
               COALESCE(SUM(fin.desconto), 0.00) AS discount_total,
               COALESCE(SUM(fin.acrescimo), 0.00) AS increase_total,
               COALESCE(SUM(receivable.valor_recebido), 0.00) AS received_total,
               COALESCE(SUM(receivable.saldo), 0.00) AS receivable_balance,
               SUM(CASE WHEN receivable.status = "paga" THEN 1 ELSE 0 END) AS paid_orders,
               SUM(CASE WHEN receivable.status IN ("pendente", "parcial", "vencida") THEN 1 ELSE 0 END) AS open_orders'
            : '0.00 AS company_total, 0.00 AS service_total, 0.00 AS product_total,
               0.00 AS other_total, 0.00 AS discount_total, 0.00 AS increase_total,
               0.00 AS received_total, 0.00 AS receivable_balance,
               0 AS paid_orders, 0 AS open_orders';
        $receivableJoin = $includeFinancial
            ? ' LEFT JOIN contas_receber receivable ON receivable.ordem_servico_id = os.id'
            : '';
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS orders, COUNT(DISTINCT os.cliente_id) AS clients, '
            . $financialColumns . '
               FROM ordem_servico_finalizacoes fin
               JOIN ordens_servico os ON os.id = fin.ordem_servico_id'
            . $receivableJoin . '
              WHERE fin.ativa = 1 AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at AND fin.finalizado_em < :end_at'
        );
        $statement->execute(['start_at' => $start, 'end_at' => $endExclusive]);

        return $statement->fetch() ?: [];
    }
    /** @return array<string,mixed> */
    public function financialSummary(string $start, string $endExclusive): array
    {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(SUM(CASE WHEN tipo = "entrada" THEN valor WHEN tipo = "estorno_entrada" THEN -valor ELSE 0 END), 0.00) AS cash_in,
                    COALESCE(SUM(CASE WHEN tipo = "saida" THEN valor WHEN tipo = "estorno_saida" THEN -valor ELSE 0 END), 0.00) AS cash_out,
                    COALESCE(SUM(CASE
                        WHEN origem_tipo = "conta_pagar_parcela" AND tipo = "saida" THEN valor
                        WHEN origem_tipo = "conta_pagar_estorno" AND tipo = "estorno_saida" THEN -valor
                        ELSE 0 END), 0.00) AS paid_expenses,
                    COALESCE(SUM(CASE
                        WHEN origem_tipo = "conta_pagar_parcela" AND tipo = "saida" THEN 1
                        WHEN origem_tipo = "conta_pagar_estorno" AND tipo = "estorno_saida" THEN -1
                        ELSE 0 END), 0) AS paid_installments,
                    COALESCE(SUM(CASE
                        WHEN origem_tipo = "venda_avulsa" AND tipo = "entrada" THEN valor
                        WHEN origem_tipo = "venda_avulsa_estorno" AND tipo = "estorno_entrada" THEN -valor
                        ELSE 0 END), 0.00) AS sales_total,
                    COALESCE(SUM(CASE
                        WHEN origem_tipo = "venda_avulsa" AND tipo = "entrada" THEN 1
                        WHEN origem_tipo = "venda_avulsa_estorno" AND tipo = "estorno_entrada" THEN -1
                        ELSE 0 END), 0) AS sales
               FROM caixa_movimentacoes
              WHERE data_movimento >= :start_at AND data_movimento < :end_at'
        );
        $statement->execute(['start_at' => $start, 'end_at' => $endExclusive]);

        return $statement->fetch() ?: [];
    }

    /** @return array<string,mixed> */
    public function inventorySummary(bool $includeCost = false): array
    {
        $costSelect = $includeCost
            ? 'CAST(COALESCE(SUM(estoque * preco_custo), 0) AS DECIMAL(18,2))'
            : 'CAST(0 AS DECIMAL(18,2))';
        $statement = $this->connection->query(
            'SELECT COUNT(*) AS active_products,
                    SUM(CASE WHEN estoque <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                    SUM(CASE WHEN estoque > 0 AND estoque <= estoque_minimo THEN 1 ELSE 0 END) AS low_stock,
                    ' . $costSelect . ' AS stock_cost_value
               FROM produtos
              WHERE status = "ativo" AND excluido_em IS NULL'
        );

        return $statement->fetch() ?: [];
    }
    /** @return array<int,array<string,mixed>> */
    public function companyOrderDetails(string $start, string $endExclusive, bool $includeFinancial = true): array
    {
        $financialColumns = $includeFinancial
            ? 'fin.subtotal_servicos AS service_total, fin.subtotal_produtos AS product_total,
               fin.subtotal_outros AS other_total, fin.desconto AS discount_total,
               fin.acrescimo AS increase_total, fin.total_executado AS executed_total,
               COALESCE(receivable.valor_recebido, 0.00) AS received_total,
               COALESCE(receivable.saldo, fin.total_executado) AS balance,
               COALESCE(receivable.status, "pendente") AS payment_status'
            : '0.00 AS service_total, 0.00 AS product_total, 0.00 AS other_total,
               0.00 AS discount_total, 0.00 AS increase_total, 0.00 AS executed_total,
               0.00 AS received_total, 0.00 AS balance, "pendente" AS payment_status';
        $receivableJoin = $includeFinancial
            ? ' LEFT JOIN contas_receber receivable ON receivable.ordem_servico_id = os.id'
            : '';
        $statement = $this->connection->prepare(
            'SELECT COALESCE(os.numero, CONCAT("OS-", LPAD(os.id, 6, "0"))) AS order_number,
                    client.nome AS client_name, fin.finalizado_em AS finalized_at, '
            . $financialColumns . '
               FROM ordem_servico_finalizacoes fin
               JOIN ordens_servico os ON os.id = fin.ordem_servico_id
               JOIN clientes client ON client.id = os.cliente_id'
            . $receivableJoin . '
              WHERE fin.ativa = 1 AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at AND fin.finalizado_em < :end_at
              ORDER BY fin.finalizado_em DESC, os.id DESC
              LIMIT 200'
        );
        $statement->execute(['start_at' => $start, 'end_at' => $endExclusive]);

        return $statement->fetchAll();
    }
    /** @return array<int,array<string,mixed>> */
    public function itemRanking(string $start, string $endExclusive, string $type, bool $includeValues = true): array
    {
        if (!in_array($type, ['servico', 'produto'], true)) {
            throw new InvalidArgumentException('Tipo de item inválido para o relatório.');
        }
        $totalSelect = $includeValues
            ? 'COALESCE(SUM(item.subtotal), 0.00)'
            : '0.00';
        $statement = $this->connection->prepare(
            'SELECT item.descricao, item.unidade,
                    COUNT(DISTINCT fin.ordem_servico_id) AS orders,
                    COALESCE(SUM(item.quantidade), 0.000) AS quantity,
                    ' . $totalSelect . ' AS total
               FROM ordem_servico_execucao_itens item
               JOIN ordem_servico_finalizacoes fin ON fin.id = item.finalizacao_id
               JOIN ordens_servico os ON os.id = fin.ordem_servico_id
              WHERE item.tipo = :item_type AND fin.ativa = 1 AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at AND fin.finalizado_em < :end_at
              GROUP BY item.descricao, item.unidade
              ORDER BY quantity DESC, item.descricao ASC
              LIMIT 10'
        );
        $statement->execute(['item_type' => $type, 'start_at' => $start, 'end_at' => $endExclusive]);

        return $statement->fetchAll();
    }
    /** @return array<int,array<string,mixed>> */
    public function employeeProduction(string $start, string $endExclusive, bool $includeValues = true): array
    {
        $valueColumns = $includeValues
            ? 'COALESCE(SUM(fin.total_executado), 0.00) AS realized,
               COALESCE(SUM(fin.subtotal_servicos), 0.00) AS service_total'
            : '0.00 AS realized, 0.00 AS service_total';
        $statement = $this->connection->prepare(
            'SELECT f.id, f.codigo, f.nome, f.funcao,
                    COALESCE(producao.orders, 0) AS orders,
                    COALESCE(producao.realized, 0.00) AS realized,
                    COALESCE(producao.service_total, 0.00) AS service_total
               FROM funcionarios f
               LEFT JOIN (
                    SELECT equipe.funcionario_id,
                           COUNT(DISTINCT fin.ordem_servico_id) AS orders, '
                    . $valueColumns . '
                      FROM ordem_servico_finalizacoes fin
                      JOIN ordens_servico os ON os.id = fin.ordem_servico_id
                      JOIN (
                           SELECT ordem_servico_id, funcionario_id
                             FROM ordem_servico_funcionarios
                            WHERE ativo = 1
                            GROUP BY ordem_servico_id, funcionario_id
                      ) equipe ON equipe.ordem_servico_id = os.id
                     WHERE fin.ativa = 1 AND os.excluida_em IS NULL
                       AND fin.finalizado_em >= :start_at AND fin.finalizado_em < :end_at
                     GROUP BY equipe.funcionario_id
               ) producao ON producao.funcionario_id = f.id
              ORDER BY orders DESC, f.nome ASC, f.id ASC'
        );
        $statement->execute(['start_at' => $start, 'end_at' => $endExclusive]);

        return $statement->fetchAll();
    }
    /** @return array<int,array<string,mixed>> */
    public function employeeOrderDetails(string $start, string $endExclusive, bool $includeValues = true): array
    {
        $valueColumns = $includeValues
            ? 'fin.subtotal_servicos AS service_total, fin.total_executado AS executed_total'
            : '0.00 AS service_total, 0.00 AS executed_total';
        $statement = $this->connection->prepare(
            'SELECT f.id AS employee_id, f.nome AS employee_name,
                    COALESCE(NULLIF(f.funcao, ""), osf.funcao) AS employee_function,
                    COALESCE(os.numero, CONCAT("OS-", LPAD(os.id, 6, "0"))) AS order_number,
                    c.nome AS client_name, fin.finalizado_em AS finalized_at, '
                    . $valueColumns . '
               FROM ordem_servico_finalizacoes fin
               JOIN ordens_servico os ON os.id = fin.ordem_servico_id
               JOIN clientes c ON c.id = os.cliente_id
               JOIN (
                    SELECT ordem_servico_id, funcionario_id, MAX(funcao) AS funcao
                      FROM ordem_servico_funcionarios
                     WHERE ativo = 1
                     GROUP BY ordem_servico_id, funcionario_id
               ) osf ON osf.ordem_servico_id = os.id
               JOIN funcionarios f ON f.id = osf.funcionario_id
              WHERE fin.ativa = 1 AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at AND fin.finalizado_em < :end_at
              ORDER BY f.nome ASC, fin.finalizado_em DESC, os.id DESC
              LIMIT 500'
        );
        $statement->execute(['start_at' => $start, 'end_at' => $endExclusive]);

        return $statement->fetchAll();
    }
}