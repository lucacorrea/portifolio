<?php

declare(strict_types=1);

namespace App\Workforce\Repository;

use App\Workforce\DTO\EmployeeFormData;
use App\Workforce\Entity\Employee;
use InvalidArgumentException;
use PDO;
use Throwable;

final class EmployeeRepository
{
    private const STATUSES = [
        'ativo',
        'inativo',
    ];

    private const LIST_COLUMNS = [
        'id',
        'codigo',
        'nome',
        'foto',
        'funcao',
        'status',
        'telefone_celular',
        'data_admissao',
        'criado_em',
        'atualizado_em',
    ];

    private const COLUMNS = [
        'id',
        'codigo',
        'nome',
        'foto',
        'funcao',
        'status',
        'salario',
        'endereco',
        'telefone_celular',
        'data_nascimento',
        'estado_civil',
        'sexo',
        'data_cadastro',
        'data_admissao',
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
        'rg_numero',
        'rg_uf',
        'rg_orgao_emissor',
        'rg_data_emissao',
        'cpf_numero',
        'titulo_eleitor_numero',
        'titulo_eleitor_uf',
        'titulo_eleitor_secao',
        'titulo_eleitor_zona',
        'reservista_numero',
        'reservista_data_emissao',
        'certidao_nascimento_numero',
        'certidao_nascimento_cidade',
        'certidao_nascimento_livro',
        'certidao_nascimento_folha',
        'certidao_nascimento_data_emissao',
        'carteira_trabalho_numero',
        'carteira_trabalho_serie',
        'carteira_trabalho_uf',
        'pis_pasep_numero',
        'cnh_numero_registro',
        'cnh_categoria',
        'cnh_data_vencimento',
        'manequim_camisa',
        'manequim_calca',
        'manequim_calcado',
        'criado_em',
        'atualizado_em',
    ];

    private const SALARY_FIELDS = [
        'salario',
    ];

    private const BANK_FIELDS = [
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
    ];

    private const DOCUMENT_FIELDS = [
        'rg_numero',
        'rg_uf',
        'rg_orgao_emissor',
        'rg_data_emissao',
        'cpf_numero',
        'titulo_eleitor_numero',
        'titulo_eleitor_uf',
        'titulo_eleitor_secao',
        'titulo_eleitor_zona',
        'reservista_numero',
        'reservista_data_emissao',
        'certidao_nascimento_numero',
        'certidao_nascimento_cidade',
        'certidao_nascimento_livro',
        'certidao_nascimento_folha',
        'certidao_nascimento_data_emissao',
        'carteira_trabalho_numero',
        'carteira_trabalho_serie',
        'carteira_trabalho_uf',
        'pis_pasep_numero',
        'cnh_numero_registro',
        'cnh_categoria',
        'cnh_data_vencimento',
    ];

    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * @return Employee[]
     */
    public function findAll(
        string $search = '',
        ?string $status = null
    ): array {
        $search = trim($search);

        if (
            strlen($search) > 150
            || str_contains($search, "\0")
        ) {
            throw new InvalidArgumentException(
                'Pesquisa de funcionário inválida.'
            );
        }

        if (
            $status !== null
            && !in_array(
                $status,
                self::STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Status de funcionário inválido.'
            );
        }

        $sql =
            'SELECT '
            . implode(
                ', ',
                self::LIST_COLUMNS
            )
            . '
             FROM funcionarios';

        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = '(
                codigo LIKE :search_code
                OR nome LIKE :search_name
                OR funcao LIKE :search_function
            )';

            $like = '%' . $search . '%';

            $parameters += [
                'search_code' => $like,
                'search_name' => $like,
                'search_function' => $like,
            ];
        }

        if ($status !== null) {
            $where[] =
                'status = :status';

            $parameters['status'] =
                $status;
        }

        if ($where !== []) {
            $sql .=
                ' WHERE '
                . implode(
                    ' AND ',
                    $where
                );
        }

        $sql .=
            ' ORDER BY
                status ASC,
                nome ASC,
                id ASC';

        $statement =
            $this->connection->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        return array_map(
            static fn (
                array $row
            ): Employee =>
                Employee::fromArray($row),

            $statement->fetchAll()
        );
    }

    public function findById(
        int $id
    ): ?Employee {
        $this->assertPositiveId($id);

        $statement =
            $this->connection->prepare(
                'SELECT '
                . implode(
                    ', ',
                    self::COLUMNS
                )
                . '
                 FROM funcionarios
                 WHERE id = :id
                 LIMIT 1'
            );

        $statement->execute([
            'id' => $id,
        ]);

        $row = $statement->fetch();

        return $row === false
            ? null
            : Employee::fromArray($row);
    }

    public function create(
        EmployeeFormData $data
    ): Employee {
        $values =
            $data->databaseValues();

        $ownsTransaction =
            !$this->connection
                ->inTransaction();

        if ($ownsTransaction) {
            $this->connection
                ->beginTransaction();
        }

        try {
            $this->assertCpfAvailable(
                $values['cpf_numero']
                    ?? null,
                null
            );

            $columns =
                array_keys($values);

            $placeholders =
                array_map(
                    static fn (
                        string $column
                    ): string =>
                        ':' . $column,

                    $columns
                );

            $statement =
                $this->connection->prepare(
                    'INSERT INTO funcionarios (
                        '
                        . implode(
                            ', ',
                            $columns
                        )
                        . '
                    )
                    VALUES (
                        '
                        . implode(
                            ', ',
                            $placeholders
                        )
                        . '
                    )'
                );

            $statement->execute($values);

            $id = (int) (
                $this->connection
                    ->lastInsertId()
            );

            $this->assertPositiveId($id);

            $code = sprintf(
                'FUN-%06d',
                $id
            );

            $update =
                $this->connection->prepare(
                    'UPDATE funcionarios
                        SET codigo = :code
                      WHERE id = :id'
                );

            $update->execute([
                'id' => $id,
                'code' => $code,
            ]);

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection
                    ->inTransaction()
            ) {
                $this->connection
                    ->rollBack();
            }

            throw $exception;
        }

        $employee =
            $this->findById($id);

        if ($employee === null) {
            throw new InvalidArgumentException(
                'Funcionário não encontrado após cadastro.'
            );
        }

        return $employee;
    }

    public function update(
        int $id,
        EmployeeFormData $data,
        bool $updateSalary = true,
        bool $updateDocuments = true,
        bool $updateBankData = true
    ): void {
        $this->assertPositiveId($id);

        $values =
            $this->filterAllowedValues(
                $data->databaseValues(),
                $updateSalary,
                $updateDocuments,
                $updateBankData
            );

        $ownsTransaction =
            !$this->connection
                ->inTransaction();

        if ($ownsTransaction) {
            $this->connection
                ->beginTransaction();
        }

        try {
            if (
                array_key_exists(
                    'cpf_numero',
                    $values
                )
            ) {
                $this->assertCpfAvailable(
                    $values['cpf_numero'],
                    $id
                );
            }

            $assignments = [];

            foreach (
                array_keys($values)
                as $column
            ) {
                $assignments[] =
                    $column
                    . ' = :'
                    . $column;
            }

            $values['employee_id'] =
                $id;

            $statement =
                $this->connection->prepare(
                    'UPDATE funcionarios
                        SET '
                        . implode(
                            ', ',
                            $assignments
                        )
                        . '
                      WHERE id = :employee_id'
                );

            $statement->execute($values);

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection
                    ->inTransaction()
            ) {
                $this->connection
                    ->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Ativa ou inativa um funcionário.
     *
     * Funcionário com OS operacional ou planejamento
     * semanal pendente deve ser reassociado antes.
     */
    public function updateStatus(
        int $id,
        string $status
    ): void {
        $this->assertPositiveId($id);

        if (
            !in_array(
                $status,
                self::STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Status de funcionário inválido.'
            );
        }

        $ownsTransaction =
            !$this->connection
                ->inTransaction();

        if ($ownsTransaction) {
            $this->connection
                ->beginTransaction();
        }

        try {
            $statement =
                $this->connection->prepare(
                    'SELECT
                        id,
                        nome,
                        status
                     FROM funcionarios
                     WHERE id = :id
                     LIMIT 1
                     FOR UPDATE'
                );

            $statement->execute([
                'id' => $id,
            ]);

            $employee = $statement->fetch();

            if ($employee === false) {
                throw new InvalidArgumentException(
                    'Funcionário não encontrado.'
                );
            }

            $currentStatus = (string) (
                $employee['status']
                ?? 'ativo'
            );

            if ($currentStatus === $status) {
                if ($ownsTransaction) {
                    $this->connection->commit();
                }

                return;
            }

            if ($status === 'inativo') {
                $this->assertCanDeactivate(
                    $id
                );
            }

            $update =
                $this->connection->prepare(
                    'UPDATE funcionarios
                        SET status = :status
                      WHERE id = :id'
                );

            $update->execute([
                'id' => $id,
                'status' => $status,
            ]);

            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException(
                    'Não foi possível alterar o status do funcionário.'
                );
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection
                    ->inTransaction()
            ) {
                $this->connection
                    ->rollBack();
            }

            throw $exception;
        }
    }

    public function updateName(
        int $id,
        string $name
    ): void {
        $this->assertPositiveId($id);

        $statement =
            $this->connection->prepare(
                'UPDATE funcionarios
                    SET nome = :name
                  WHERE id = :id'
            );

        $statement->execute([
            'id' => $id,
            'name' => $name,
        ]);
    }

    public function updateEmployeePhoto(
        int $id,
        ?string $photoPath
    ): void {
        $this->assertPositiveId($id);

        if (
            $photoPath !== null
            && (
                strlen($photoPath) > 255
                || str_contains(
                    $photoPath,
                    "\0"
                )
            )
        ) {
            throw new InvalidArgumentException(
                'Caminho da foto do funcionário é inválido.'
            );
        }

        $statement =
            $this->connection->prepare(
                'UPDATE funcionarios
                    SET foto = :photo
                  WHERE id = :id'
            );

        $statement->execute([
            'id' => $id,
            'photo' => $photoPath,
        ]);
    }

    private function assertCanDeactivate(
        int $employeeId
    ): void {
        $orderStatement =
            $this->connection->prepare(
                'SELECT
                    ordem.id,
                    ordem.numero
                 FROM ordem_servico_funcionarios
                    AS equipe

                 JOIN ordens_servico
                    AS ordem
                   ON ordem.id =
                      equipe.ordem_servico_id

                 WHERE equipe.funcionario_id
                       = :employee_id

                   AND equipe.ativo = 1

                   AND ordem.excluida_em
                       IS NULL

                   AND ordem.status
                       NOT IN (
                            "finalizada",
                            "cancelada"
                       )

                 ORDER BY ordem.id DESC
                 LIMIT 1
                 FOR UPDATE'
            );

        $orderStatement->execute([
            'employee_id' => $employeeId,
        ]);

        $order = $orderStatement->fetch();

        if ($order !== false) {
            $orderNumber = trim(
                (string) (
                    $order['numero']
                    ?? ''
                )
            );

            if ($orderNumber === '') {
                $orderNumber =
                    'OS #'
                    . (int) $order['id'];
            }

            throw new InvalidArgumentException(
                'O funcionário está vinculado à '
                . $orderNumber
                . '. Reatribua ou finalize essa OS antes de inativá-lo.'
            );
        }

        $planningStatement =
            $this->connection->prepare(
                'SELECT
                    id,
                    codigo
                 FROM servicos_semanais

                 WHERE status =
                       "aguardando_confirmacao"

                   AND (
                        funcionario_principal_id
                            = :primary_employee_id

                        OR

                        funcionario_apoio_id
                            = :support_employee_id
                   )

                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE'
            );

        $planningStatement->execute([
            'primary_employee_id' =>
                $employeeId,

            'support_employee_id' =>
                $employeeId,
        ]);

        $planning =
            $planningStatement->fetch();

        if ($planning !== false) {
            $planningCode = trim(
                (string) (
                    $planning['codigo']
                    ?? ''
                )
            );

            if ($planningCode === '') {
                $planningCode =
                    'planejamento #'
                    . (int) $planning['id'];
            }

            throw new InvalidArgumentException(
                'O funcionário está vinculado ao '
                . $planningCode
                . '. Altere a equipe do planejamento antes de inativá-lo.'
            );
        }
    }

    /**
     * @param array<string,string|null> $values
     *
     * @return array<string,string|null>
     */
    private function filterAllowedValues(
        array $values,
        bool $updateSalary,
        bool $updateDocuments,
        bool $updateBankData
    ): array {
        foreach (
            [
                [
                    self::SALARY_FIELDS,
                    $updateSalary,
                ],

                [
                    self::DOCUMENT_FIELDS,
                    $updateDocuments,
                ],

                [
                    self::BANK_FIELDS,
                    $updateBankData,
                ],
            ]
            as [
                $fields,
                $allowed,
            ]
        ) {
            if ($allowed) {
                continue;
            }

            foreach ($fields as $field) {
                unset($values[$field]);
            }
        }

        return $values;
    }

    private function assertCpfAvailable(
        ?string $cpf,
        ?int $ignoredId
    ): void {
        if (
            $cpf === null
            || $cpf === ''
        ) {
            return;
        }

        $sql =
            'SELECT id
               FROM funcionarios
              WHERE cpf_numero = :cpf';

        $parameters = [
            'cpf' => $cpf,
        ];

        if ($ignoredId !== null) {
            $sql .=
                ' AND id <> :ignored_id';

            $parameters['ignored_id'] =
                $ignoredId;
        }

        $sql .=
            ' LIMIT 1 FOR UPDATE';

        $statement =
            $this->connection->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        if (
            $statement->fetchColumn()
            !== false
        ) {
            throw new InvalidArgumentException(
                'Já existe um funcionário cadastrado com este CPF.'
            );
        }
    }

    private function assertPositiveId(
        int $id
    ): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID de funcionário inválido.'
            );
        }
    }
}