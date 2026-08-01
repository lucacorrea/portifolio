<?php
declare(strict_types=1);

namespace App\Access\DTO;

final class ProfileListItem
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly ?string $description,
        private readonly bool $protected,
        private readonly string $status,
        private readonly int $totalUsers,
        private readonly int $totalPermissions,
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            (string) $data['nome'],
            isset($data['descricao']) ? (string) $data['descricao'] : null,
            (bool) $data['protegido'],
            (string) $data['status'],
            (int) $data['total_usuarios'],
            (int) $data['total_permissoes'],
            isset($data['criado_em']) ? (string) $data['criado_em'] : null,
            isset($data['atualizado_em']) ? (string) $data['atualizado_em'] : null
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isProtected(): bool
    {
        return $this->protected;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function totalUsers(): int
    {
        return $this->totalUsers;
    }

    public function totalPermissions(): int
    {
        return $this->totalPermissions;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }
}
