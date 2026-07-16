<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use InvalidArgumentException;

final class ServiceOrderTeamMemberData
{
    private const ALLOWED_ROLES = [
        'Responsável técnico',
        'Técnico',
        'Instalador',
        'Auxiliar',
        'Eletricista',
        'Supervisor',
        'Motorista',
        'Outro',
    ];

    public function __construct(
        private readonly int $employeeId,
        private readonly string $role,
        private readonly bool $primary
    ) {
        if ($this->employeeId <= 0) {
            throw new InvalidArgumentException('Informe um funcionário válido.');
        }

        if ($this->role === '' || self::textLength($this->role) > 80 || str_contains($this->role, "\0") || $this->role !== strip_tags($this->role)) {
            throw new InvalidArgumentException('Informe uma função válida para a equipe.');
        }
    }

    public static function fromArray(array $data): self
    {
        $role = self::canonicalRole(trim((string) ($data['funcao'] ?? $data['role'] ?? 'Técnico')));
        if ($role === 'Outro') {
            $customRole = trim((string) ($data['funcao_personalizada'] ?? $data['custom_role'] ?? ''));
            if ($customRole !== '') {
                $role = $customRole;
            }
        }

        if (!in_array($role, self::ALLOWED_ROLES, true) && self::textLength($role) > 80) {
            throw new InvalidArgumentException('Função operacional inválida.');
        }

        return new self(
            self::positiveInt($data['funcionario_id'] ?? $data['employee_id'] ?? null),
            $role,
            self::boolValue($data['principal'] ?? $data['primary'] ?? false)
        );
    }

    private static function canonicalRole(string $role): string
    {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($role, 'UTF-8') : strtolower($role);
        return match ($normalized) {
            'responsavel tecnico', 'responsavel técnico', 'responsável tecnico', 'responsável técnico' => 'Responsável técnico',
            'tecnico', 'técnico' => 'Técnico',
            default => $role,
        };
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function primary(): bool
    {
        return $this->primary;
    }

    private static function positiveInt(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($int)) {
            throw new InvalidArgumentException('Informe um funcionário válido.');
        }

        return $int;
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function boolValue(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'sim'], true);
    }
}
