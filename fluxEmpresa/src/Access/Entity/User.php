<?php
declare(strict_types=1);

namespace App\Access\Entity;

use InvalidArgumentException;

final class User
{
    private const VALID_STATUSES = ['ativo', 'inativo', 'bloqueado'];

    public function __construct(
        private readonly ?int $id,
        private readonly int $profileId,
        private string $name,
        private string $username,
        private string $email,
        private readonly string $passwordHash,
        private readonly ?string $phone,
        private string $status,
        private readonly bool $mustChangePassword,
        private readonly int $failedAttempts,
        private readonly ?string $lockedUntil = null,
        private readonly ?string $lastAccess = null,
        private readonly ?string $passwordChangedAt = null,
        private readonly ?string $createdAt = null,
        private readonly ?string $updatedAt = null
    ) {
        if ($profileId <= 0) {
            throw new InvalidArgumentException('Perfil do usuario invalido.');
        }

        $this->name = $this->validateRequired($name, 'Nome', 150);
        $this->username = $this->validateUsername($username);
        $this->email = $this->validateEmail($email);
        $this->status = $this->validateStatus($status);

        if (trim($passwordHash) === '') {
            throw new InvalidArgumentException('Hash de senha e obrigatorio.');
        }

        if ($failedAttempts < 0) {
            throw new InvalidArgumentException('Tentativas falhas nao pode ser negativo.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int) $data['id'] : null,
            (int) ($data['perfil_id'] ?? 0),
            (string) ($data['nome'] ?? ''),
            (string) ($data['usuario'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['senha_hash'] ?? ''),
            isset($data['telefone']) ? (string) $data['telefone'] : null,
            (string) ($data['status'] ?? 'ativo'),
            (bool) ($data['deve_alterar_senha'] ?? false),
            (int) ($data['tentativas_falhas'] ?? 0),
            isset($data['bloqueado_ate']) ? (string) $data['bloqueado_ate'] : null,
            isset($data['ultimo_acesso']) ? (string) $data['ultimo_acesso'] : null,
            isset($data['senha_alterada_em']) ? (string) $data['senha_alterada_em'] : null,
            isset($data['criado_em']) ? (string) $data['criado_em'] : null,
            isset($data['atualizado_em']) ? (string) $data['atualizado_em'] : null
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function profileId(): int
    {
        return $this->profileId;
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

    public function passwordHash(): string
    {
        return $this->passwordHash;
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

    public function passwordChangedAt(): ?string
    {
        return $this->passwordChangedAt;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    private function validateRequired(string $value, string $label, int $maxLength): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            throw new InvalidArgumentException($label . ' do usuario e obrigatorio.');
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException($label . ' do usuario excede o limite.');
        }

        return $value;
    }

    private function validateUsername(string $username): string
    {
        $username = trim($username);

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) {
            throw new InvalidArgumentException('Usuario invalido.');
        }

        return $username;
    }

    private function validateEmail(string $email): string
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new InvalidArgumentException('E-mail invalido.');
        }

        return strtolower($email);
    }

    private function validateStatus(string $status): string
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Status de usuario invalido.');
        }

        return $status;
    }
}
