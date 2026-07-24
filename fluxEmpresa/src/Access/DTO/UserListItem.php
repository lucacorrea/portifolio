<?php

declare(strict_types=1);

namespace App\Access\DTO;

final class UserListItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $profileId,
        private readonly string $profileName,
        private readonly string $name,
        private readonly string $username,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly string $status,
        private readonly bool $mustChangePassword,
        private readonly int $failedAttempts,
        private readonly ?string $lockedUntil,
        private readonly ?string $lastAccess,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            profileId: (int) ($data['perfil_id'] ?? 0),
            profileName: (string) ($data['perfil_nome'] ?? ''),
            name: (string) ($data['nome'] ?? ''),
            username: (string) ($data['usuario'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phone: isset($data['telefone'])
                ? (string) $data['telefone']
                : null,
            status: (string) ($data['status'] ?? 'inativo'),
            mustChangePassword: (bool) (
                $data['deve_alterar_senha'] ?? false
            ),
            failedAttempts: (int) (
                $data['tentativas_falhas'] ?? 0
            ),
            lockedUntil: isset($data['bloqueado_ate'])
                ? (string) $data['bloqueado_ate']
                : null,
            lastAccess: isset($data['ultimo_acesso'])
                ? (string) $data['ultimo_acesso']
                : null,
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function profileId(): int
    {
        return $this->profileId;
    }

    public function profileName(): string
    {
        return $this->profileName;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function failedAttempts(): int
    {
        return $this->failedAttempts;
    }

    public function lockedUntil(): ?string
    {
        return $this->lockedUntil;
    }

    public function lastAccess(): ?string
    {
        return $this->lastAccess;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }

    public function initials(): string
    {
        $words = preg_split(
            '/\s+/',
            trim($this->name)
        ) ?: [];

        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            if ($word === '') {
                continue;
            }

            $initials .= mb_strtoupper(
                mb_substr($word, 0, 1)
            );
        }

        return $initials !== ''
            ? $initials
            : 'US';
    }

    public function isTemporarilyLocked(): bool
    {
        if (
            $this->lockedUntil === null
            || trim($this->lockedUntil) === ''
        ) {
            return false;
        }

        try {
            return new \DateTimeImmutable(
                $this->lockedUntil
            ) > new \DateTimeImmutable();
        } catch (\Throwable) {
            return false;
        }
    }

    public function statusLabel(): string
    {
        if ($this->isTemporarilyLocked()) {
            return 'Bloqueio temporário';
        }

        return match ($this->status) {
            'ativo' => 'Ativo',
            'bloqueado' => 'Bloqueado',
            'inativo' => 'Inativo',
            default => 'Desconhecido',
        };
    }
}

