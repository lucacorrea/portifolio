<?php

declare(strict_types=1);

namespace App\Report\Repository;

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
    public function summary(string $start, string $endExclusive): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS orders,
                    COALESCE(SUM(fin.total_executado), 0.00) AS company_total,
                    COALESCE(SUM(fin.subtotal_servicos), 0.00) AS service_total
               FROM ordem_servico_finalizacoes fin
               JOIN ordens_servico os ON os.id = fin.ordem_servico_id
              WHERE fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
        );
        $statement->execute([
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetch() ?: [
            'orders' => 0,
            'company_total' => '0.00',
            'service_total' => '0.00',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function employeeProduction(string $start, string $endExclusive): array
    {
        $statement = $this->connection->prepare(
            'SELECT f.id, f.codigo, f.nome, f.funcao,
                    COALESCE(producao.orders, 0) AS orders,
                    COALESCE(producao.realized, 0.00) AS realized,
                    COALESCE(producao.service_total, 0.00) AS service_total
               FROM funcionarios f
               LEFT JOIN (
                    SELECT equipe.funcionario_id,
                           COUNT(DISTINCT fin.ordem_servico_id) AS orders,
                           COALESCE(SUM(fin.total_executado), 0.00) AS realized,
                           COALESCE(SUM(fin.subtotal_servicos), 0.00) AS service_total
                      FROM ordem_servico_finalizacoes fin
                      JOIN ordens_servico os ON os.id = fin.ordem_servico_id
                      JOIN (
                           SELECT ordem_servico_id, funcionario_id
                             FROM ordem_servico_funcionarios
                            WHERE ativo = 1
                            GROUP BY ordem_servico_id, funcionario_id
                      ) equipe ON equipe.ordem_servico_id = os.id
                     WHERE fin.ativa = 1
                       AND os.excluida_em IS NULL
                       AND fin.finalizado_em >= :start_at
                       AND fin.finalizado_em < :end_at
                     GROUP BY equipe.funcionario_id
               ) producao ON producao.funcionario_id = f.id
              ORDER BY realized DESC, f.nome ASC, f.id ASC'
        );
        $statement->execute([
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function employeeOrderDetails(string $start, string $endExclusive): array
    {
        $statement = $this->connection->prepare(
            'SELECT f.id AS employee_id, f.nome AS employee_name,
                    COALESCE(NULLIF(f.funcao, \'\'), osf.funcao) AS employee_function,
                    COALESCE(os.numero, CONCAT(\'OS-\', LPAD(os.id, 6, \'0\'))) AS order_number,
                    c.nome AS client_name, fin.finalizado_em AS finalized_at,
                    fin.subtotal_servicos AS service_total,
                    fin.total_executado AS executed_total
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
              WHERE fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at
              ORDER BY f.nome ASC, fin.finalizado_em DESC, os.id DESC'
        );
        $statement->execute([
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetchAll();
    }
}
