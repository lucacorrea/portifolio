<?php

declare(strict_types=1);

namespace App\Report\Repository;

use App\Company\DTO\CompanyScope;
use PDO;
use PDOStatement;

final class ServiceExecutionPrintRepository
{
    /*
     * Para clientes da Prefeitura, equipamento_ambiente representa
     * a secretaria. Para clientes comuns, o nome do cliente passa
     * a representar a unidade responsável pelo atendimento.
     */
    private const SECRETARIAT_SQL = "COALESCE(
        NULLIF(TRIM(os.equipamento_ambiente), ''),
        CASE
            WHEN LOWER(c.nome) LIKE '%prefeitura%'
                THEN 'SECRETARIA NÃO INFORMADA'
            ELSE c.nome
        END
    )";

    /*
     * equipamento_local é a fonte principal.
     * Na ausência dele, utiliza o endereço do cliente.
     */
    private const LOCATION_SQL = "COALESCE(
        NULLIF(TRIM(os.equipamento_local), ''),
        NULLIF(
            TRIM(
                CONCAT_WS(
                    ', ',
                    NULLIF(TRIM(c.endereco), ''),
                    NULLIF(TRIM(c.numero), ''),
                    NULLIF(TRIM(c.bairro), ''),
                    NULLIF(TRIM(c.cidade), '')
                )
            ),
            ''
        ),
        'LOCAL NÃO INFORMADO'
    )";

    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope
    ) {
    }

    /**
     * Lista somente clientes que possuem serviço ou item
     * executado no período.
     *
     * @return array<int,array{id:int,code:string,name:string}>
     */
    public function clients(
        string $start,
        string $endExclusive
    ): array {
        $statement = $this->connection->prepare(
            "SELECT DISTINCT
                    c.id,
                    COALESCE(c.codigo, '') AS code,
                    c.nome AS name

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               JOIN ordem_servico_execucao_itens item
                 ON item.finalizacao_id = fin.id
                AND item.empresa_id = fin.empresa_id

              WHERE fin.empresa_id = :company_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND item.tipo IN ('servico', 'outro')
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at

              ORDER BY c.nome ASC, c.id ASC"
        );

        $this->execute(
            $statement,
            [
                'company_id' => $this->companyScope->id(),
                'start_at' => $start,
                'end_at' => $endExclusive,
            ]
        );

        return array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ],
            $statement->fetchAll()
        );
    }

    /**
     * @return string[]
     */
    public function secretariats(
        string $start,
        string $endExclusive,
        ?int $clientId
    ): array {
        $sql = "SELECT DISTINCT "
            . self::SECRETARIAT_SQL
            . " AS secretariat

                  FROM ordem_servico_finalizacoes fin

                  JOIN ordens_servico os
                    ON os.id = fin.ordem_servico_id
                   AND os.empresa_id = fin.empresa_id

                  JOIN clientes c
                    ON c.id = os.cliente_id
                   AND c.empresa_id = os.empresa_id

                  JOIN ordem_servico_execucao_itens item
                    ON item.finalizacao_id = fin.id
                   AND item.empresa_id = fin.empresa_id

                 WHERE fin.empresa_id = :company_id
                   AND fin.ativa = 1
                   AND os.excluida_em IS NULL
                   AND item.tipo IN ('servico', 'outro')
                   AND fin.finalizado_em >= :start_at
                   AND fin.finalizado_em < :end_at";

        $params = [
            'company_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ];

        if ($clientId !== null) {
            $sql .= ' AND c.id = :client_id';

            $params['client_id'] = $clientId;
        }

        $sql .= ' ORDER BY secretariat ASC';

        $statement = $this->connection->prepare($sql);

        $this->execute(
            $statement,
            $params
        );

        $values = [];

        foreach ($statement->fetchAll() as $row) {
            $value = trim(
                (string) ($row['secretariat'] ?? '')
            );

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @return string[]
     */
    public function locations(
        string $start,
        string $endExclusive,
        ?int $clientId,
        string $secretariat
    ): array {
        $sql = "SELECT DISTINCT "
            . self::LOCATION_SQL
            . " AS location_name

                  FROM ordem_servico_finalizacoes fin

                  JOIN ordens_servico os
                    ON os.id = fin.ordem_servico_id
                   AND os.empresa_id = fin.empresa_id

                  JOIN clientes c
                    ON c.id = os.cliente_id
                   AND c.empresa_id = os.empresa_id

                  JOIN ordem_servico_execucao_itens item
                    ON item.finalizacao_id = fin.id
                   AND item.empresa_id = fin.empresa_id

                 WHERE fin.empresa_id = :company_id
                   AND fin.ativa = 1
                   AND os.excluida_em IS NULL
                   AND item.tipo IN ('servico', 'outro')
                   AND fin.finalizado_em >= :start_at
                   AND fin.finalizado_em < :end_at";

        $params = [
            'company_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ];

        if ($clientId !== null) {
            $sql .= ' AND c.id = :client_id';

            $params['client_id'] = $clientId;
        }

        if ($secretariat !== '') {
            $sql .= ' AND '
                . self::SECRETARIAT_SQL
                . ' = :secretariat';

            $params['secretariat'] = $secretariat;
        }

        $sql .= ' ORDER BY location_name ASC';

        $statement = $this->connection->prepare($sql);

        $this->execute(
            $statement,
            $params
        );

        $values = [];

        foreach ($statement->fetchAll() as $row) {
            $value = trim(
                (string) ($row['location_name'] ?? '')
            );

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Busca os dados completos da impressão.
     *
     * Inclui:
     *
     * - secretaria ou cliente;
     * - local;
     * - cliente;
     * - OS;
     * - equipe;
     * - diagnóstico;
     * - solução;
     * - serviços;
     * - itens importados de aquisições;
     * - valores históricos da finalização.
     *
     * @return array<int,array<string,mixed>>
     */
    public function rows(
        string $start,
        string $endExclusive,
        ?int $clientId,
        string $secretariat,
        string $location,
        string $search
    ): array {
        $sql = "SELECT
                    fin.id AS finalization_id,
                    fin.finalizado_em AS finalized_at,
                    fin.subtotal_servicos AS service_total,
                    fin.subtotal_produtos AS product_total,
                    fin.subtotal_outros AS other_total,
                    fin.desconto AS order_discount,
                    fin.acrescimo AS order_addition,
                    fin.total_executado AS executed_total,

                    os.id AS order_id,

                    COALESCE(
                        NULLIF(TRIM(os.numero), ''),
                        CONCAT(
                            'OS-',
                            LPAD(os.id, 6, '0')
                        )
                    ) AS order_number,

                    os.equipamento_ambiente AS raw_secretariat,
                    os.equipamento_local AS raw_location,
                    os.problema_relatado AS reported_problem,
                    os.problema_identificado AS identified_problem,
                    os.diagnostico AS diagnosis,
                    os.solucao AS solution,
                    os.recomendacao AS recommendation,

                    c.id AS client_id,
                    c.codigo AS client_code,
                    c.nome AS client_name,
                    c.documento AS client_document,
                    c.telefone AS client_phone,

                    CASE
                        WHEN LOWER(c.nome) LIKE '%prefeitura%'
                            THEN 1
                        ELSE 0
                    END AS is_public_client,

                    "
            . self::SECRETARIAT_SQL
            . " AS secretariat,

                    "
            . self::LOCATION_SQL
            . " AS location_name,

                    COALESCE(
                        team.team_members,
                        'EQUIPE NÃO INFORMADA'
                    ) AS team_members,

                    COALESCE(
                        team.employee_ids,
                        ''
                    ) AS employee_ids,

                    COALESCE(
                        team.employee_count,
                        0
                    ) AS employee_count,

                    item.id AS item_id,
                    item.tipo AS item_type,
                    item.referencia_id AS item_reference_id,
                    item.descricao AS item_description,
                    item.unidade AS item_unit,
                    item.quantidade AS item_quantity,
                    item.valor_unitario AS item_unit_value,
                    item.desconto AS item_discount,
                    item.subtotal AS item_subtotal

               FROM ordem_servico_finalizacoes fin

               JOIN ordens_servico os
                 ON os.id = fin.ordem_servico_id
                AND os.empresa_id = fin.empresa_id

               JOIN clientes c
                 ON c.id = os.cliente_id
                AND c.empresa_id = os.empresa_id

               JOIN ordem_servico_execucao_itens item
                 ON item.finalizacao_id = fin.id
                AND item.empresa_id = fin.empresa_id

               LEFT JOIN (
                    SELECT
                        osf.empresa_id,
                        osf.ordem_servico_id,

                        GROUP_CONCAT(
                            DISTINCT f.nome
                            ORDER BY
                                osf.principal DESC,
                                f.nome ASC
                            SEPARATOR ', '
                        ) AS team_members,

                        GROUP_CONCAT(
                            DISTINCT f.id
                            ORDER BY f.id ASC
                            SEPARATOR ','
                        ) AS employee_ids,

                        COUNT(
                            DISTINCT f.id
                        ) AS employee_count

                      FROM ordem_servico_funcionarios osf

                      JOIN funcionarios f
                        ON f.id = osf.funcionario_id
                       AND f.empresa_id = osf.empresa_id

                     WHERE osf.empresa_id =
                           :team_company_id

                       AND osf.ativo = 1

                     GROUP BY
                         osf.empresa_id,
                         osf.ordem_servico_id
               ) team
                 ON team.empresa_id = os.empresa_id
                AND team.ordem_servico_id = os.id

              WHERE fin.empresa_id = :company_id
                AND fin.ativa = 1
                AND os.excluida_em IS NULL
                AND item.tipo IN ('servico', 'outro')
                AND fin.finalizado_em >= :start_at
                AND fin.finalizado_em < :end_at";

        $params = [
            'team_company_id' => $this->companyScope->id(),
            'company_id' => $this->companyScope->id(),
            'start_at' => $start,
            'end_at' => $endExclusive,
        ];

        if ($clientId !== null) {
            $sql .= ' AND c.id = :client_id';

            $params['client_id'] = $clientId;
        }

        if ($secretariat !== '') {
            $sql .= ' AND '
                . self::SECRETARIAT_SQL
                . ' = :secretariat';

            $params['secretariat'] = $secretariat;
        }

        if ($location !== '') {
            $sql .= ' AND '
                . self::LOCATION_SQL
                . ' = :location_name';

            $params['location_name'] = $location;
        }

        if ($search !== '') {
            $sql .= " AND (
                os.numero LIKE :search_order
                OR c.nome LIKE :search_client
                OR item.descricao LIKE :search_item
                OR "
                . self::SECRETARIAT_SQL
                . " LIKE :search_secretariat
                OR "
                . self::LOCATION_SQL
                . " LIKE :search_location
                OR COALESCE(
                    team.team_members,
                    ''
                ) LIKE :search_team
                OR COALESCE(
                    os.problema_identificado,
                    ''
                ) LIKE :search_problem
                OR COALESCE(
                    os.diagnostico,
                    ''
                ) LIKE :search_diagnosis
                OR COALESCE(
                    os.solucao,
                    ''
                ) LIKE :search_solution
            )";

            $pattern = '%' . $search . '%';

            $params['search_order'] = $pattern;
            $params['search_client'] = $pattern;
            $params['search_item'] = $pattern;
            $params['search_secretariat'] = $pattern;
            $params['search_location'] = $pattern;
            $params['search_team'] = $pattern;
            $params['search_problem'] = $pattern;
            $params['search_diagnosis'] = $pattern;
            $params['search_solution'] = $pattern;
        }

        $sql .= " ORDER BY
                    secretariat ASC,
                    location_name ASC,
                    fin.finalizado_em ASC,
                    os.id ASC,
                    item.id ASC";

        $statement = $this->connection->prepare($sql);

        $this->execute(
            $statement,
            $params
        );

        return $statement->fetchAll();
    }

    /**
     * @param array<string,mixed> $params
     */
    private function execute(
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
}