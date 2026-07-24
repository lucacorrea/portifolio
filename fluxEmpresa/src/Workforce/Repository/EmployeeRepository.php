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
    private const LIST_COLUMNS = [
        'id', 'codigo', 'nome', 'foto', 'funcao', 'telefone_celular',
        'data_admissao', 'criado_em', 'atualizado_em',
    ];

    private const COLUMNS = [
        'id', 'codigo', 'nome', 'foto', 'funcao', 'salario', 'endereco',
        'telefone_celular', 'data_nascimento', 'estado_civil', 'sexo',
        'data_cadastro', 'data_admissao', 'banco', 'agencia', 'conta',
        'tipo_conta', 'pix', 'rg_numero', 'rg_uf', 'rg_orgao_emissor',
        'rg_data_emissao', 'cpf_numero', 'titulo_eleitor_numero',
        'titulo_eleitor_uf', 'titulo_eleitor_secao', 'titulo_eleitor_zona',
        'reservista_numero', 'reservista_data_emissao',
        'certidao_nascimento_numero', 'certidao_nascimento_cidade',
        'certidao_nascimento_livro', 'certidao_nascimento_folha',
        'certidao_nascimento_data_emissao', 'carteira_trabalho_numero',
        'carteira_trabalho_serie', 'carteira_trabalho_uf', 'pis_pasep_numero',
        'cnh_numero_registro', 'cnh_categoria', 'cnh_data_vencimento',
        'manequim_camisa', 'manequim_calca', 'manequim_calcado',
        'criado_em', 'atualizado_em',
    ];

    private const SALARY_FIELDS = ['salario'];
    private const BANK_FIELDS = ['banco', 'agencia', 'conta', 'tipo_conta', 'pix'];
    private const DOCUMENT_FIELDS = [
        'rg_numero', 'rg_uf', 'rg_orgao_emissor', 'rg_data_emissao', 'cpf_numero',
        'titulo_eleitor_numero', 'titulo_eleitor_uf', 'titulo_eleitor_secao',
        'titulo_eleitor_zona', 'reservista_numero', 'reservista_data_emissao',
        'certidao_nascimento_numero', 'certidao_nascimento_cidade',
        'certidao_nascimento_livro', 'certidao_nascimento_folha',
        'certidao_nascimento_data_emissao', 'carteira_trabalho_numero',
        'carteira_trabalho_serie', 'carteira_trabalho_uf', 'pis_pasep_numero',
        'cnh_numero_registro', 'cnh_categoria', 'cnh_data_vencimento',
    ];

    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return Employee[] */
    public function findAll(string $search = ''): array
    {
        $search = trim($search);
        $sql = 'SELECT ' . implode(', ', self::LIST_COLUMNS) . ' FROM funcionarios';
        $parameters = [];

        if ($search !== '') {
            $sql .= ' WHERE codigo LIKE :search_code
                       OR nome LIKE :search_name
                       OR funcao LIKE :search_function';
            $like = '%' . $search . '%';
            $parameters = [
                'search_code' => $like,
                'search_name' => $like,
                'search_function' => $like,
            ];
        }

        $sql .= ' ORDER BY nome ASC, id ASC';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return array_map(
            static fn(array $row): Employee => Employee::fromArray($row),
            $statement->fetchAll()
        );
    }

    public function findById(int $id): ?Employee
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'SELECT ' . implode(', ', self::COLUMNS) . '
               FROM funcionarios
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Employee::fromArray($row);
    }

    public function create(EmployeeFormData $data): Employee
    {
        $values = $data->databaseValues();
        $this->connection->beginTransaction();

        try {
            $this->assertCpfAvailable($values['cpf_numero'] ?? null, null);
            $columns = array_keys($values);
            $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
            $statement = $this->connection->prepare(
                'INSERT INTO funcionarios (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $statement->execute($values);

            $id = (int) $this->connection->lastInsertId();
            $this->assertPositiveId($id);
            $code = sprintf('FUN-%06d', $id);
            $update = $this->connection->prepare(
                'UPDATE funcionarios SET codigo = :code WHERE id = :id'
            );
            $update->execute(['id' => $id, 'code' => $code]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        $employee = $this->findById($id);
        if ($employee === null) {
            throw new InvalidArgumentException('Funcionário não encontrado após cadastro.');
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
        $values = $this->filterAllowedValues(
            $data->databaseValues(),
            $updateSalary,
            $updateDocuments,
            $updateBankData
        );

        $this->connection->beginTransaction();
        try {
            if (array_key_exists('cpf_numero', $values)) {
                $this->assertCpfAvailable($values['cpf_numero'], $id);
            }
            $assignments = [];
            foreach (array_keys($values) as $column) {
                $assignments[] = $column . ' = :' . $column;
            }
            $values['employee_id'] = $id;
            $statement = $this->connection->prepare(
                'UPDATE funcionarios SET ' . implode(', ', $assignments) . '
                  WHERE id = :employee_id'
            );
            $statement->execute($values);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateName(int $id, string $name): void
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'UPDATE funcionarios SET nome = :name WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'name' => $name]);
    }

    public function updateEmployeePhoto(int $id, ?string $photoPath): void
    {
        $this->assertPositiveId($id);
        if ($photoPath !== null && (strlen($photoPath) > 255 || str_contains($photoPath, "\0"))) {
            throw new InvalidArgumentException('Caminho da foto do funcionário é inválido.');
        }
        $statement = $this->connection->prepare(
            'UPDATE funcionarios SET foto = :photo WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'photo' => $photoPath]);
    }

    /**
     * @param array<string, string|null> $values
     * @return array<string, string|null>
     */
    private function filterAllowedValues(
        array $values,
        bool $updateSalary,
        bool $updateDocuments,
        bool $updateBankData
    ): array {
        foreach ([
            [self::SALARY_FIELDS, $updateSalary],
            [self::DOCUMENT_FIELDS, $updateDocuments],
            [self::BANK_FIELDS, $updateBankData],
        ] as [$fields, $allowed]) {
            if ($allowed) {
                continue;
            }
            foreach ($fields as $field) {
                unset($values[$field]);
            }
        }

        return $values;
    }

    private function assertCpfAvailable(?string $cpf, ?int $ignoredId): void
    {
        if ($cpf === null || $cpf === '') {
            return;
        }
        $sql = 'SELECT id FROM funcionarios WHERE cpf_numero = :cpf';
        $parameters = ['cpf' => $cpf];
        if ($ignoredId !== null) {
            $sql .= ' AND id <> :ignored_id';
            $parameters['ignored_id'] = $ignoredId;
        }
        $sql .= ' LIMIT 1 FOR UPDATE';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        if ($statement->fetchColumn() !== false) {
            throw new InvalidArgumentException('Já existe um funcionário cadastrado com este CPF.');
        }
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de funcionário inválido.');
        }
    }
}
