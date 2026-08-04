<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Company\DTO\CompanyScope;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

final class ProductionReportRepository
{
    private const SERVICE_NORMALIZED_DESCRIPTION_SQL =
        "LOWER(REGEXP_REPLACE(TRIM(item.descricao), '[[:space:]]+', ' '))";

    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope
    ) {
    }

    /** @return array<string,mixed>|null */
    public function activeGoal(string $competence): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id,
                    competencia,
                    versao,
                    valor_meta,
                    percentual_comissao,
                    criada_por,
                    criada_em
               FROM metas_comissao_mensais
              WHERE empresa_id = :empresa_id
                AND competencia = :competence
                AND ativa = 1
              LIMIT 1'
        );

        $this->executeStatement($statement, [
            'empresa_id' => $this->companyScope->id(),
            'competence' => $competence,
        ]);

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
                  WHERE empresa_id = :empresa_id
                    AND competencia = :competence
                  FOR UPDATE'
            );

            $this->executeStatement($versionStatement, [
                'empresa_id' => $this->companyScope->id(),
                'competence' => $competence,
            ]);

            $version = ((int) ($versionStatement->fetchColumn() ?: 0)) + 1;

            $deactivateStatement = $this->connection->prepare(
                'UPDATE metas_comissao_mensais
                    SET ativa = 0,
                        desativada_por = :user_id,
                        desativada_em = CURRENT_TIMESTAMP
                  WHERE empresa_id = :empresa_id
                    AND competencia = :competence
                    AND ativa = 1'
            );

            $this->executeStatement($deactivateStatement, [
                'empresa_id' => $this->companyScope->id(),
                'competence' => $competence,
                'user_id' => $userId,
            ]);

            $insertStatement = $this->connection->prepare(
                'INSERT INTO metas_comissao_mensais
                    (
                        empresa_id,
                        competencia,
                        versao,
                        valor_meta,
                        percentual_comissao,
                        ativa,
                        criada_por
                    )
                 VALUES
                    (
                        :empresa_id,
                        :competence,
                        :version,
                        :goal_amount,
                        :commission_percentage,
                        1,
                        :user_id
                    )'
            );

            $this->executeStatement($insertStatement, [
                'empresa_id' => $this->companyScope->id(),
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

    /**
     * Contrato legado utilizado por monthlyReport().
     *
     * @return array<string,mixed>
     */
    public function summary(string $start, string $endExclusive): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(DISTINCT fin.ordem_servico_id) AS orders,
                    COALESCE(SUM(fin.total_executado), 0.00) AS company_total,
                    COALESCE(SUM(fin.subtotal_servicos), 0.00) AS service_total
               FROM ordem_servico_finalizacoes fin
               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id
               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id
              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
        );

        $this->executeStatement($statement, [
            'empresa_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetch() ?: [
            'orders' => 0,
            'company_total' => '0.00',
            'service_total' => '0.00',
        ];
    }

    /** @return array<string,mixed> */
    public function companySummary(
        string $start,
        string $endExclusive
    ): array {
        $statement = $this->connection->prepare(
            'SELECT COUNT(DISTINCT fin.ordem_servico_id) AS orders,
                    COUNT(DISTINCT os.cliente_id) AS unique_clients,

                    COALESCE(
                        SUM(fin.total_executado),
                        0.00
                    ) AS company_total,

                    COALESCE(
                        SUM(fin.subtotal_servicos),
                        0.00
                    ) AS service_total,

                    COALESCE(
                        SUM(fin.subtotal_produtos),
                        0.00
                    ) AS product_total,

                    COALESCE(
                        SUM(fin.subtotal_outros),
                        0.00
                    ) AS other_total,

                    COALESCE(
                        SUM(fin.desconto),
                        0.00
                    ) AS discount_total,

                    COALESCE(
                        SUM(fin.acrescimo),
                        0.00
                    ) AS addition_total,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN cr.id IS NOT NULL
                                 AND cr.status <> \'cancelada\'
                                    THEN cr.valor_recebido
                                ELSE 0.00
                            END
                        ),
                        0.00
                    ) AS received_total,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN cr.id IS NOT NULL
                                 AND cr.status <> \'cancelada\'
                                    THEN cr.saldo
                                ELSE 0.00
                            END
                        ),
                        0.00
                    ) AS receivable_balance,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN cr.id IS NOT NULL
                                 AND cr.status IN (
                                     \'pendente\',
                                     \'parcial\',
                                     \'vencida\'
                                 )
                                 AND cr.saldo > 0.00
                                    THEN 1
                                ELSE 0
                            END
                        ),
                        0
                    ) AS pending_accounts,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN cr.id IS NOT NULL
                                 AND cr.status <> \'cancelada\'
                                 AND cr.saldo > 0.00
                                 AND (
                                     cr.status = \'vencida\'
                                     OR (
                                         cr.status IN (
                                             \'pendente\',
                                             \'parcial\'
                                         )
                                         AND cr.vencimento_em IS NOT NULL
                                         AND cr.vencimento_em < CURRENT_DATE
                                     )
                                 )
                                    THEN 1
                                ELSE 0
                            END
                        ),
                        0
                    ) AS overdue_accounts

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               LEFT JOIN contas_receber cr
                 ON cr.ordem_servico_id = os.id
                AND cr.empresa_id = os.empresa_id

              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
        );

        $this->executeStatement($statement, [
            'empresa_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetch() ?: $this->emptyCompanySummary();
    }

    /** @return array<string,mixed> */
    public function companyPreviousPeriodSummary(
        string $start,
        string $endExclusive
    ): array {
        return $this->companySummary($start, $endExclusive);
    }

    /** @return array<int,array<string,mixed>> */
    public function companyDailyEvolution(
        string $start,
        string $endExclusive
    ): array {
        $statement = $this->connection->prepare(
            'SELECT DATE(fin.finalizado_em) AS reference_date,
                    COUNT(DISTINCT fin.ordem_servico_id) AS orders,
                    COALESCE(
                        SUM(fin.total_executado),
                        0.00
                    ) AS company_total

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at

              GROUP BY DATE(fin.finalizado_em)
              ORDER BY reference_date ASC'
        );

        $this->executeStatement($statement, [
            'empresa_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function clientSummary(
        string $start,
        string $endExclusive,
        string $search,
        string $sort,
        string $direction,
        int $limit,
        int $offset
    ): array {
        $this->assertPagination($limit, $offset);

        [$searchSql, $searchParams] = $this->clientSearch($search);

        $orderBy = $this->clientOrderBy($sort);
        $orderDirection = $this->orderDirection($direction);

        $statement = $this->connection->prepare(
            'SELECT c.id AS client_id,
                    c.codigo AS code,
                    c.nome AS name,
                    c.documento AS document,
                    c.telefone AS phone,

                    COUNT(
                        DISTINCT fin.ordem_servico_id
                    ) AS order_count,

                    COALESCE(
                        SUM(fin.total_executado),
                        0.00
                    ) AS executed_total,

                    COALESCE(
                        SUM(fin.subtotal_servicos),
                        0.00
                    ) AS service_total,

                    COALESCE(
                        SUM(fin.subtotal_produtos),
                        0.00
                    ) AS product_total,

                    COALESCE(
                        SUM(fin.subtotal_outros),
                        0.00
                    ) AS other_total,

                    COALESCE(
                        SUM(fin.desconto),
                        0.00
                    ) AS discount_total,

                    COALESCE(
                        SUM(fin.acrescimo),
                        0.00
                    ) AS addition_total,

                    COALESCE(
                        SUM(fin.total_executado)
                        / NULLIF(
                            COUNT(DISTINCT fin.ordem_servico_id),
                            0
                        ),
                        0.00
                    ) AS average_ticket,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN cr.id IS NOT NULL
                                 AND cr.status <> \'cancelada\'
                                    THEN cr.valor_recebido
                                ELSE 0.00
                            END
                        ),
                        0.00
                    ) AS received_total,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN cr.id IS NOT NULL
                                 AND cr.status <> \'cancelada\'
                                    THEN cr.saldo
                                ELSE 0.00
                            END
                        ),
                        0.00
                    ) AS pending_balance,

                    MIN(fin.finalizado_em) AS first_finalized_at,
                    MAX(fin.finalizado_em) AS last_finalized_at

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               LEFT JOIN contas_receber cr
                 ON cr.ordem_servico_id = os.id
                AND cr.empresa_id = os.empresa_id

              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
            . $searchSql .
            ' GROUP BY
                    c.id,
                    c.codigo,
                    c.nome,
                    c.documento,
                    c.telefone

              ORDER BY '
            . $orderBy
            . ' '
            . $orderDirection
            . ', c.id ASC

              LIMIT :limit_rows
              OFFSET :offset_rows'
        );

        $this->executeStatement(
            $statement,
            array_merge(
                [
                    'empresa_id' => $this->companyScope->id(),
                    'start_at' => $start,
                    'end_at' => $endExclusive,
                    'limit_rows' => $limit,
                    'offset_rows' => $offset,
                ],
                $searchParams
            )
        );

        return $statement->fetchAll();
    }

    public function countClientSummary(
        string $start,
        string $endExclusive,
        string $search
    ): int {
        [$searchSql, $searchParams] = $this->clientSearch($search);

        $statement = $this->connection->prepare(
            'SELECT COUNT(DISTINCT c.id)

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
            . $searchSql
        );

        $this->executeStatement(
            $statement,
            array_merge(
                [
                    'empresa_id' => $this->companyScope->id(),
                    'start_at' => $start,
                    'end_at' => $endExclusive,
                ],
                $searchParams
            )
        );

        return (int) $statement->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function clientOrderDetails(
        int $clientId,
        string $start,
        string $endExclusive,
        int $limit = 100,
        int $offset = 0
    ): array {
        $this->assertPositiveId(
            $clientId,
            'Cliente inválido.'
        );

        $this->assertPagination($limit, $offset);

        $statement = $this->connection->prepare(
            'SELECT os.id AS order_id,

                    COALESCE(
                        os.numero,
                        CONCAT(
                            \'OS-\',
                            LPAD(os.id, 6, \'0\')
                        )
                    ) AS order_number,

                    fin.finalizado_em AS finalized_at,
                    fin.total_executado AS executed_total,
                    fin.subtotal_servicos AS service_total,
                    fin.subtotal_produtos AS product_total,
                    fin.subtotal_outros AS other_total,

                    COALESCE(
                        team.team_members,
                        \'Equipe não informada\'
                    ) AS team_members,

                    COALESCE(
                        cr.status,
                        \'sem_conta\'
                    ) AS financial_status,

                    COALESCE(
                        CASE
                            WHEN cr.status <> \'cancelada\'
                                THEN cr.valor_recebido
                            ELSE 0.00
                        END,
                        0.00
                    ) AS received_total,

                    COALESCE(
                        CASE
                            WHEN cr.status <> \'cancelada\'
                                THEN cr.saldo
                            ELSE 0.00
                        END,
                        0.00
                    ) AS pending_balance

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               LEFT JOIN contas_receber cr
                 ON cr.ordem_servico_id = os.id
                AND cr.empresa_id = os.empresa_id

               LEFT JOIN (
                    SELECT osf.empresa_id,
                           osf.ordem_servico_id,

                           GROUP_CONCAT(
                               DISTINCT CONCAT(
                                   f.nome,
                                   CASE
                                       WHEN osf.principal = 1
                                           THEN \' (principal)\'
                                       ELSE \'\'
                                   END
                               )
                               ORDER BY
                                   osf.principal DESC,
                                   f.nome ASC
                               SEPARATOR \', \'
                           ) AS team_members

                      FROM ordem_servico_funcionarios osf

                      JOIN funcionarios f
                        ON f.id = osf.funcionario_id
                       AND f.empresa_id = osf.empresa_id

                     WHERE osf.empresa_id = :team_company_id
                       AND osf.ativo = 1

                     GROUP BY
                         osf.empresa_id,
                         osf.ordem_servico_id
               ) team
                 ON team.ordem_servico_id = os.id
                AND team.empresa_id = os.empresa_id

              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND c.id = :client_id
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at

              ORDER BY
                  fin.finalizado_em DESC,
                  os.id DESC

              LIMIT :limit_rows
              OFFSET :offset_rows'
        );

        $this->executeStatement($statement, [
            'team_company_id' => $this->companyScope->id(),
            'empresa_id' => $this->companyScope->id(),
            'client_id' => $clientId,
            'start_at' => $start,
            'end_at' => $endExclusive,
            'limit_rows' => $limit,
            'offset_rows' => $offset,
        ]);

        return $statement->fetchAll();
    }

    public function countClientOrderDetails(
        int $clientId,
        string $start,
        string $endExclusive
    ): int {
        $this->assertPositiveId(
            $clientId,
            'Cliente inválido.'
        );

        $statement = $this->connection->prepare(
            'SELECT COUNT(
                        DISTINCT fin.ordem_servico_id
                    )

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

              WHERE fin.empresa_id = :empresa_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND c.id = :client_id
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
        );

        $this->executeStatement($statement, [
            'empresa_id' => $this->companyScope->id(),
            'client_id' => $clientId,
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function topClientsByRevenue(
        string $start,
        string $endExclusive,
        int $limit = 10
    ): array {
        return $this->clientSummary(
            $start,
            $endExclusive,
            '',
            'revenue',
            'desc',
            $limit,
            0
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function serviceSummary(
        string $start,
        string $endExclusive,
        string $search,
        string $sort,
        string $direction,
        int $limit,
        int $offset
    ): array {
        $this->assertPagination($limit, $offset);

        [$searchSql, $searchParams] = $this->serviceSearch($search);

        $orderBy = $this->serviceOrderBy($sort);
        $orderDirection = $this->orderDirection($direction);
        $normalizedDescription =
            self::SERVICE_NORMALIZED_DESCRIPTION_SQL;
        $groupKey = $this->serviceGroupKeySql();

        $statement = $this->connection->prepare(
            'SELECT '
            . $groupKey .
            ' AS group_key,

                    item.referencia_id AS service_id,
                    MAX(s.codigo) AS code,
                    MIN(item.descricao) AS historical_description,
                    MAX(s.nome) AS current_name,

                    CASE
                        WHEN item.referencia_id IS NULL
                            THEN \'manual\'
                        ELSE \'registered\'
                    END AS origin,

                    COALESCE(
                        SUM(item.quantidade),
                        0.000
                    ) AS quantity_total,

                    COUNT(
                        DISTINCT fin.ordem_servico_id
                    ) AS order_count,

                    COUNT(
                        DISTINCT os.cliente_id
                    ) AS client_count,

                    COALESCE(
                        SUM(item.subtotal),
                        0.00
                    ) AS revenue_total,

                    COALESCE(
                        SUM(item.desconto),
                        0.00
                    ) AS discount_total,

                    COALESCE(
                        SUM(
                            item.quantidade
                            * item.valor_unitario
                        )
                        / NULLIF(
                            SUM(item.quantidade),
                            0
                        ),
                        0.00
                    ) AS average_unit_value,

                    COALESCE(
                        SUM(item.subtotal)
                        / NULLIF(
                            COUNT(
                                DISTINCT fin.ordem_servico_id
                            ),
                            0
                        ),
                        0.00
                    ) AS average_order_ticket,

                    MIN(fin.finalizado_em) AS first_executed_at,
                    MAX(fin.finalizado_em) AS last_executed_at

               FROM ordem_servico_execucao_itens item

               JOIN ordem_servico_finalizacoes fin
                 ON fin.id = item.finalizacao_id
                AND fin.empresa_id = item.empresa_id

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = item.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               LEFT JOIN servicos s
                 ON s.id = item.referencia_id
                AND s.empresa_id = item.empresa_id

              WHERE item.empresa_id = :empresa_id
                AND item.tipo = \'servico\'
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
            . $searchSql .
            ' GROUP BY
                    item.referencia_id,
                    '
            . $normalizedDescription .
            ' ORDER BY '
            . $orderBy
            . ' '
            . $orderDirection
            . ', group_key ASC

              LIMIT :limit_rows
              OFFSET :offset_rows'
        );

        $this->executeStatement(
            $statement,
            array_merge(
                [
                    'empresa_id' => $this->companyScope->id(),
                    'start_at' => $start,
                    'end_at' => $endExclusive,
                    'limit_rows' => $limit,
                    'offset_rows' => $offset,
                ],
                $searchParams
            )
        );

        return $statement->fetchAll();
    }

    public function countServiceSummary(
        string $start,
        string $endExclusive,
        string $search
    ): int {
        [$searchSql, $searchParams] = $this->serviceSearch($search);

        $normalizedDescription =
            self::SERVICE_NORMALIZED_DESCRIPTION_SQL;

        $statement = $this->connection->prepare(
            'SELECT COUNT(*)

               FROM (
                    SELECT item.referencia_id,
                           '
            . $normalizedDescription .
            ' AS normalized_description

                      FROM ordem_servico_execucao_itens item

                      JOIN ordem_servico_finalizacoes fin
                        ON fin.id = item.finalizacao_id
                       AND fin.empresa_id = item.empresa_id

                      JOIN ordens_servico os
                        ON os.id = fin.ordem_servico_id
                       AND os.empresa_id = item.empresa_id

                      JOIN clientes c
                        ON c.id = os.cliente_id
                       AND c.empresa_id = os.empresa_id

                      LEFT JOIN servicos s
                        ON s.id = item.referencia_id
                       AND s.empresa_id = item.empresa_id

                     WHERE item.empresa_id = :empresa_id
                       AND item.tipo = \'servico\'
                       AND fin.ativa = 1
                       AND os.excluida_em IS NULL
                       AND fin.finalizado_em >= :start_at
                       AND fin.finalizado_em < :end_at'
            . $searchSql .
            ' GROUP BY
                    item.referencia_id,
                    '
            . $normalizedDescription .
            '
               ) grouped_services'
        );

        $this->executeStatement(
            $statement,
            array_merge(
                [
                    'empresa_id' => $this->companyScope->id(),
                    'start_at' => $start,
                    'end_at' => $endExclusive,
                ],
                $searchParams
            )
        );

        return (int) $statement->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function serviceExecutionDetails(
        ?int $serviceId,
        string $descriptionHash,
        string $start,
        string $endExclusive,
        int $limit = 100,
        int $offset = 0
    ): array {
        if ($serviceId !== null) {
            $this->assertPositiveId(
                $serviceId,
                'Serviço inválido.'
            );
        }

        $this->assertDescriptionHash($descriptionHash);
        $this->assertPagination($limit, $offset);

        $serviceFilter = $serviceId === null
            ? ' AND item.referencia_id IS NULL'
            : ' AND item.referencia_id = :service_id';

        $statement = $this->connection->prepare(
            'SELECT os.id AS order_id,

                    COALESCE(
                        os.numero,
                        CONCAT(
                            \'OS-\',
                            LPAD(os.id, 6, \'0\')
                        )
                    ) AS order_number,

                    c.id AS client_id,
                    c.nome AS client_name,
                    fin.finalizado_em AS executed_at,
                    item.descricao AS historical_description,
                    item.quantidade AS quantity,
                    item.unidade AS unit,
                    item.valor_unitario AS unit_value,
                    item.desconto AS discount,
                    item.subtotal AS subtotal,

                    COALESCE(
                        team.team_members,
                        \'Equipe não informada\'
                    ) AS team_members

               FROM ordem_servico_execucao_itens item

               JOIN ordem_servico_finalizacoes fin
                 ON fin.id = item.finalizacao_id
                AND fin.empresa_id = item.empresa_id

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = item.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               LEFT JOIN (
                    SELECT osf.empresa_id,
                           osf.ordem_servico_id,

                           GROUP_CONCAT(
                               DISTINCT CONCAT(
                                   f.nome,
                                   CASE
                                       WHEN osf.principal = 1
                                           THEN \' (principal)\'
                                       ELSE \'\'
                                   END
                               )
                               ORDER BY
                                   osf.principal DESC,
                                   f.nome ASC
                               SEPARATOR \', \'
                           ) AS team_members

                      FROM ordem_servico_funcionarios osf

                      JOIN funcionarios f
                        ON f.id = osf.funcionario_id
                       AND f.empresa_id = osf.empresa_id

                     WHERE osf.empresa_id = :team_company_id
                       AND osf.ativo = 1

                     GROUP BY
                         osf.empresa_id,
                         osf.ordem_servico_id
               ) team
                 ON team.ordem_servico_id = os.id
                AND team.empresa_id = os.empresa_id

              WHERE item.empresa_id = :empresa_id
                AND item.tipo = \'servico\'
                AND fin.ativa = 1
                AND os.excluida_em IS NULL

                AND SHA2(
                    '
            . self::SERVICE_NORMALIZED_DESCRIPTION_SQL .
            ',
                    256
                ) = :description_hash

                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
            . $serviceFilter .
            ' ORDER BY
                  fin.finalizado_em DESC,
                  os.id DESC,
                  item.id ASC

              LIMIT :limit_rows
              OFFSET :offset_rows'
        );

        $params = [
            'team_company_id' => $this->companyScope->id(),
            'empresa_id' => $this->companyScope->id(),
            'description_hash' => strtolower($descriptionHash),
            'start_at' => $start,
            'end_at' => $endExclusive,
            'limit_rows' => $limit,
            'offset_rows' => $offset,
        ];

        if ($serviceId !== null) {
            $params['service_id'] = $serviceId;
        }

        $this->executeStatement($statement, $params);

        return $statement->fetchAll();
    }

    public function countServiceExecutionDetails(
        ?int $serviceId,
        string $descriptionHash,
        string $start,
        string $endExclusive
    ): int {
        if ($serviceId !== null) {
            $this->assertPositiveId(
                $serviceId,
                'Serviço inválido.'
            );
        }

        $this->assertDescriptionHash($descriptionHash);

        $serviceFilter = $serviceId === null
            ? ' AND item.referencia_id IS NULL'
            : ' AND item.referencia_id = :service_id';

        $statement = $this->connection->prepare(
            'SELECT COUNT(*)

               FROM ordem_servico_execucao_itens item

               JOIN ordem_servico_finalizacoes fin
                 ON fin.id = item.finalizacao_id
                AND fin.empresa_id = item.empresa_id

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = item.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

              WHERE item.empresa_id = :empresa_id
                AND item.tipo = \'servico\'
                AND fin.ativa = 1
                AND os.excluida_em IS NULL

                AND SHA2(
                    '
            . self::SERVICE_NORMALIZED_DESCRIPTION_SQL .
            ',
                    256
                ) = :description_hash

                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at'
            . $serviceFilter
        );

        $params = [
            'empresa_id' => $this->companyScope->id(),
            'description_hash' => strtolower($descriptionHash),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ];

        if ($serviceId !== null) {
            $params['service_id'] = $serviceId;
        }

        $this->executeStatement($statement, $params);

        return (int) $statement->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function topServicesByRevenue(
        string $start,
        string $endExclusive,
        int $limit = 10
    ): array {
        return $this->serviceSummary(
            $start,
            $endExclusive,
            '',
            'revenue',
            'desc',
            $limit,
            0
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function topServicesByQuantity(
        string $start,
        string $endExclusive,
        int $limit = 10
    ): array {
        return $this->serviceSummary(
            $start,
            $endExclusive,
            '',
            'quantity',
            'desc',
            $limit,
            0
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function employeeProduction(
        string $start,
        string $endExclusive
    ): array {
        $statement = $this->connection->prepare(
            'SELECT f.id,
                    f.codigo,
                    f.nome,
                    f.funcao,

                    COALESCE(
                        production.orders,
                        0
                    ) AS orders,

                    COALESCE(
                        production.realized,
                        0.00
                    ) AS realized,

                    COALESCE(
                        production.service_total,
                        0.00
                    ) AS service_total

               FROM funcionarios f

               LEFT JOIN (
                    SELECT team.funcionario_id,

                           COUNT(
                               DISTINCT fin.ordem_servico_id
                           ) AS orders,

                           COALESCE(
                               SUM(fin.total_executado),
                               0.00
                           ) AS realized,

                           COALESCE(
                               SUM(fin.subtotal_servicos),
                               0.00
                           ) AS service_total

                      FROM ordem_servico_finalizacoes fin

                      JOIN ordens_servico os
                        ON os.id = fin.ordem_servico_id
                       AND os.empresa_id = fin.empresa_id

                      JOIN (
                           SELECT ordem_servico_id,
                                  funcionario_id

                             FROM ordem_servico_funcionarios

                            WHERE empresa_id = :team_company_id
                              AND ativo = 1

                            GROUP BY
                                ordem_servico_id,
                                funcionario_id
                      ) team
                        ON team.ordem_servico_id = os.id

                     WHERE fin.empresa_id =
                           :finalization_company_id

                       AND fin.ativa = 1
                       AND os.excluida_em IS NULL
                       AND fin.finalizado_em >= :start_at
                       AND fin.finalizado_em < :end_at

                     GROUP BY team.funcionario_id
               ) production
                 ON production.funcionario_id = f.id

              WHERE f.empresa_id = :employee_company_id

              ORDER BY
                  realized DESC,
                  f.nome ASC,
                  f.id ASC'
        );

        $this->executeStatement($statement, [
            'team_company_id' => $this->companyScope->id(),
            'finalization_company_id' => $this->companyScope->id(),
            'employee_company_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function employeeOrderDetails(
        string $start,
        string $endExclusive
    ): array {
        $statement = $this->connection->prepare(
            'SELECT f.id AS employee_id,
                    f.nome AS employee_name,

                    COALESCE(
                        NULLIF(f.funcao, \'\'),
                        osf.funcao
                    ) AS employee_function,

                    os.id AS order_id,

                    COALESCE(
                        os.numero,
                        CONCAT(
                            \'OS-\',
                            LPAD(os.id, 6, \'0\')
                        )
                    ) AS order_number,

                    c.nome AS client_name,
                    fin.finalizado_em AS finalized_at,
                    fin.subtotal_servicos AS service_total,
                    fin.total_executado AS executed_total

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               JOIN (
                    SELECT ordem_servico_id,
                           funcionario_id,
                           MAX(funcao) AS funcao

                      FROM ordem_servico_funcionarios

                     WHERE empresa_id = :team_company_id
                       AND ativo = 1

                     GROUP BY
                         ordem_servico_id,
                         funcionario_id
               ) osf
                 ON osf.ordem_servico_id = os.id

               JOIN funcionarios f
                 ON f.id = osf.funcionario_id
                AND f.empresa_id = fin.empresa_id

              WHERE fin.empresa_id =
                    :finalization_company_id

                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at

              ORDER BY
                  f.nome ASC,
                  fin.finalizado_em DESC,
                  os.id DESC'
        );

        $this->executeStatement($statement, [
            'team_company_id' => $this->companyScope->id(),
            'finalization_company_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ]);

        return $statement->fetchAll();
    }

    /**
     * @return array{
     *     0:string,
     *     1:array<string,string>
     * }
     */
    private function clientSearch(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return ['', []];
        }

        $pattern = '%' . $search . '%';

        return [
            ' AND (
                c.nome LIKE :search_name
                OR c.documento LIKE :search_document
                OR c.codigo LIKE :search_code
                OR c.telefone LIKE :search_phone
            )',
            [
                'search_name' => $pattern,
                'search_document' => $pattern,
                'search_code' => $pattern,
                'search_phone' => $pattern,
            ],
        ];
    }

    /**
     * @return array{
     *     0:string,
     *     1:array<string,string>
     * }
     */
    private function serviceSearch(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return ['', []];
        }

        $pattern = '%' . $search . '%';

        return [
            ' AND (
                item.descricao LIKE :search_description
                OR s.codigo LIKE :search_code
                OR s.nome LIKE :search_current_name
            )',
            [
                'search_description' => $pattern,
                'search_code' => $pattern,
                'search_current_name' => $pattern,
            ],
        ];
    }

    private function clientOrderBy(string $sort): string
    {
        return match ($sort) {
            'orders' => 'order_count',
            'name' => 'name',
            'last_order' => 'last_finalized_at',
            'balance' => 'pending_balance',
            'revenue' => 'executed_total',
            default => 'executed_total',
        };
    }

    private function serviceOrderBy(string $sort): string
    {
        return match ($sort) {
            'quantity' => 'quantity_total',
            'orders' => 'order_count',
            'clients' => 'client_count',
            'description' => 'historical_description',
            'last_execution' => 'last_executed_at',
            'revenue' => 'revenue_total',
            default => 'revenue_total',
        };
    }

    private function orderDirection(string $direction): string
    {
        return strtolower(trim($direction)) === 'asc'
            ? 'ASC'
            : 'DESC';
    }

    private function serviceGroupKeySql(): string
    {
        return 'CASE
            WHEN item.referencia_id IS NULL
                THEN CONCAT(
                    \'manual:\',
                    SHA2(
                        '
            . self::SERVICE_NORMALIZED_DESCRIPTION_SQL .
            ',
                        256
                    )
                )
            ELSE CONCAT(
                \'registered:\',
                item.referencia_id,
                \':\',
                SHA2(
                    '
            . self::SERVICE_NORMALIZED_DESCRIPTION_SQL .
            ',
                    256
                )
            )
        END';
    }

    private function assertPagination(
        int $limit,
        int $offset
    ): void {
        if (
            $limit < 1
            || $limit > 100
            || $offset < 0
        ) {
            throw new InvalidArgumentException(
                'Paginação inválida para o relatório.'
            );
        }
    }

    private function assertPositiveId(
        int $id,
        string $message
    ): void {
        if ($id <= 0) {
            throw new InvalidArgumentException($message);
        }
    }

    private function assertDescriptionHash(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $hash) !== 1) {
            throw new InvalidArgumentException(
                'Agrupamento de serviço inválido.'
            );
        }
    }

    /** @param array<string,mixed> $params */
    private function executeStatement(
        PDOStatement $statement,
        array $params
    ): void {
        foreach ($params as $name => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $statement->bindValue(
                ':' . $name,
                $value,
                $type
            );
        }

        $statement->execute();
    }

    /** @return array<string,mixed> */
    private function emptyCompanySummary(): array
    {
        return [
            'orders' => 0,
            'unique_clients' => 0,
            'company_total' => '0.00',
            'service_total' => '0.00',
            'product_total' => '0.00',
            'other_total' => '0.00',
            'discount_total' => '0.00',
            'addition_total' => '0.00',
            'received_total' => '0.00',
            'receivable_balance' => '0.00',
            'pending_accounts' => 0,
            'overdue_accounts' => 0,
        ];
    }
}