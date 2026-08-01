<?php

declare(strict_types=1);

namespace App\ServiceOrder\Entity;

final class ServiceOrderTeamMember
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
        private readonly int $employeeId,
        private readonly string $employeeName,
        private readonly ?string $employeeCode,
        private readonly string $role,
        private readonly bool $primary,
        private readonly bool $active
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            orderId: (int) ($data['ordem_servico_id'] ?? 0),
            employeeId: (int) ($data['funcionario_id'] ?? 0),
            employeeName: (string) ($data['funcionario_nome'] ?? ''),
            employeeCode: isset($data['funcionario_codigo']) ? (string) $data['funcionario_codigo'] : null,
            role: (string) ($data['funcao'] ?? 'Técnico'),
            primary: (int) ($data['principal'] ?? 0) === 1,
            active: (int) ($data['ativo'] ?? 1) === 1
        );
    }

    public function id(): int { return $this->id; }
    public function orderId(): int { return $this->orderId; }
    public function employeeId(): int { return $this->employeeId; }
    public function employeeName(): string { return $this->employeeName; }
    public function employeeCode(): ?string { return $this->employeeCode; }
    public function role(): string { return $this->role; }
    public function primary(): bool { return $this->primary; }
    public function active(): bool { return $this->active; }

    public function displayName(): string
    {
        return ($this->employeeCode ?: sprintf('FUN-%06d', $this->employeeId)) . ' — ' . $this->employeeName;
    }

    public function displayLine(): string
    {
        return $this->employeeName . ' — ' . ($this->primary ? 'Principal' : $this->role);
    }
}
