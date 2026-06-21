<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use InvalidArgumentException;

final class ServiceOrderTeamData
{
    public function __construct(
        private readonly int $primaryEmployeeId,
        private readonly int $supportEmployeeId
    ) {
        if ($this->primaryEmployeeId <= 0 || $this->supportEmployeeId <= 0) {
            throw new InvalidArgumentException('Informe os dois funcionários da OS.');
        }

        if ($this->primaryEmployeeId === $this->supportEmployeeId) {
            throw new InvalidArgumentException('Funcionário principal e de apoio devem ser diferentes.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            self::positiveInt($data['funcionario_principal_id'] ?? $data['primary_employee_id'] ?? null, 'funcionário principal'),
            self::positiveInt($data['funcionario_apoio_id'] ?? $data['support_employee_id'] ?? null, 'funcionário de apoio')
        );
    }

    public function primaryEmployeeId(): int { return $this->primaryEmployeeId; }
    public function supportEmployeeId(): int { return $this->supportEmployeeId; }

    private static function positiveInt(mixed $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($int)) {
            throw new InvalidArgumentException('Informe um ' . $field . ' válido.');
        }

        return $int;
    }
}
