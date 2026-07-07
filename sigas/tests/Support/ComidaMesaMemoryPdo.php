<?php

declare(strict_types=1);

namespace App\Core;

if (!function_exists(__NAMESPACE__ . '\\mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (\function_exists('mb_substr')) {
            return \mb_substr($string, $start, $length, $encoding);
        }

        return $length === null ? \substr($string, $start) : \substr($string, $start, $length);
    }
}

namespace App\DTO;

if (!function_exists(__NAMESPACE__ . '\\mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (\function_exists('mb_substr')) {
            return \mb_substr($string, $start, $length, $encoding);
        }

        return $length === null ? \substr($string, $start) : \substr($string, $start, $length);
    }
}

namespace App\Repositories;

if (!function_exists(__NAMESPACE__ . '\\mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (\function_exists('mb_substr')) {
            return \mb_substr($string, $start, $length, $encoding);
        }

        return $length === null ? \substr($string, $start) : \substr($string, $start, $length);
    }
}

namespace App\Services;

if (!function_exists(__NAMESPACE__ . '\\mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        if (\function_exists('mb_substr')) {
            return \mb_substr($string, $start, $length, $encoding);
        }

        return $length === null ? \substr($string, $start) : \substr($string, $start, $length);
    }
}

if (!function_exists(__NAMESPACE__ . '\\mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        return \function_exists('mb_strlen') ? \mb_strlen($string, $encoding) : \strlen($string);
    }
}

namespace Tests\Support;

use PDO;
use PDOStatement;

final class ComidaMesaMemoryPdo extends PDO
{
    /** @var array<string,mixed> */
    private array $fixtures;

    /** @var list<ComidaMesaMemoryStatement> */
    public array $statements = [];

    private bool $transaction = false;

    /** @param array<string,mixed> $fixtures */
    public function __construct(array $fixtures = [])
    {
        $this->fixtures = $fixtures;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $statement = new ComidaMesaMemoryStatement($this, $query);
        $this->statements[] = $statement;

        return $statement;
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string
    {
        return (string) ($this->fixtures['last_insert_id'] ?? 9001);
    }

    /** @return list<string> */
    public function executedSql(): array
    {
        return array_values(array_map(
            static fn (ComidaMesaMemoryStatement $statement): string => $statement->sql,
            array_filter($this->statements, static fn (ComidaMesaMemoryStatement $statement): bool => $statement->executed)
        ));
    }

    public function latestStatementContaining(string $needle): ?ComidaMesaMemoryStatement
    {
        $needle = strtolower($needle);

        for ($index = count($this->statements) - 1; $index >= 0; $index--) {
            if (str_contains(strtolower($this->statements[$index]->sql), $needle)) {
                return $this->statements[$index];
            }
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    public function rowsFor(ComidaMesaMemoryStatement $statement): array
    {
        $sql = $this->normalized($statement->sql);

        if (str_contains($sql, 'select count(*)') && str_contains($sql, 'from comida_mesa_inscricoes i')) {
            return [[$this->fixtures['paginate_total'] ?? count($this->fixtures['paginate_rows'] ?? [])]];
        }

        if (str_contains($sql, 'select i.id as inscricao_id')) {
            return $this->fixtures['paginate_rows'] ?? [];
        }

        if (str_contains($sql, 'from pessoas p') && str_contains($sql, 'where p.cpf = :cpf')) {
            $cpf = (string) ($statement->boundValue(':cpf') ?? '');
            $rows = $this->fixtures['cpf_rows'] ?? [];

            return isset($rows[$cpf]) ? [$rows[$cpf]] : [];
        }

        if (str_contains($sql, 'from comida_mesa_competencias') && str_contains($sql, 'for update')) {
            return $this->singleFixture('locked_competence');
        }

        if (str_contains($sql, 'from comida_mesa_inscricoes') && str_contains($sql, 'for update')) {
            return $this->singleFixture('locked_registration');
        }

        if (str_contains($sql, 'from comida_mesa_entregas') && str_contains($sql, 'for update')) {
            return $this->singleFixture('locked_delivery');
        }

        if (str_contains($sql, 'from comida_mesa_competencias where id = :id')) {
            return $this->singleFixture('competence_by_id');
        }

        if (str_contains($sql, "from comida_mesa_competencias where status = 'aberta'")) {
            return $this->singleFixture('default_competence');
        }

        if (str_contains($sql, 'from comida_mesa_inscricoes i') && str_contains($sql, 'inner join familias f')) {
            return $this->singleFixture('detail_registration');
        }

        if (str_contains($sql, 'select * from pessoas where cpf = :cpf')) {
            return $this->singleFixture('person_by_cpf');
        }

        if (str_contains($sql, 'from familias f') && str_contains($sql, 'left join familia_membros fm')) {
            return $this->singleFixture('family_link');
        }

        if (str_contains($sql, 'select * from comida_mesa_inscricoes where familia_id = :familia_id')) {
            return $this->singleFixture('registration_by_family');
        }

        if (str_contains($sql, 'from comida_mesa_entregas e')) {
            return $this->fixtures['detail_deliveries'] ?? [];
        }

        if (str_contains($sql, 'from familia_membros fm inner join pessoas p')) {
            return $this->fixtures['detail_members'] ?? [];
        }

        if (str_contains($sql, 'from comida_mesa_documentos d')) {
            return $this->fixtures['detail_documents'] ?? [];
        }

        if (str_contains($sql, 'from comida_mesa_historico h')) {
            return $this->fixtures['detail_history'] ?? [];
        }

        return [];
    }

    private function normalized(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql);
    }

    /** @return list<array<string,mixed>> */
    private function singleFixture(string $key): array
    {
        return isset($this->fixtures[$key]) && is_array($this->fixtures[$key])
            ? [$this->fixtures[$key]]
            : [];
    }
}

final class ComidaMesaMemoryStatement extends PDOStatement
{
    public bool $executed = false;

    /** @var array<string,array{value:mixed,type:int}> */
    public array $bindings = [];

    /** @var list<array<string,mixed>> */
    private array $rows = [];

    private int $cursor = 0;

    public function __construct(
        private readonly ComidaMesaMemoryPdo $pdo,
        public readonly string $sql,
    ) {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$this->normalizeParam($param)] = ['value' => $value, 'type' => $type];

        return true;
    }

    public function execute(?array $params = null): bool
    {
        foreach ($params ?? [] as $key => $value) {
            $this->bindValue(is_int($key) ? $key : ':' . ltrim((string) $key, ':'), $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $this->executed = true;
        $this->rows = $this->pdo->rowsFor($this);
        $this->cursor = 0;

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[$this->cursor++] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? [];

        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        return reset($row);
    }

    public function boundValue(string|int $param): mixed
    {
        return $this->bindings[$this->normalizeParam($param)]['value'] ?? null;
    }

    public function boundType(string|int $param): ?int
    {
        return $this->bindings[$this->normalizeParam($param)]['type'] ?? null;
    }

    private function normalizeParam(string|int $param): string
    {
        return is_int($param) ? (string) $param : ':' . ltrim($param, ':');
    }
}
