<?php
declare(strict_types=1);

namespace App\Access\Entity;

use InvalidArgumentException;

final class Profile
{
    private const VALID_STATUSES = ['ativo', 'inativo'];
    private readonly string $code;

    public function __construct(
        private readonly ?int $id,
        private string $name,
        private readonly ?string $description,
        private readonly bool $protected,
        private string $status,
        private readonly ?string $createdAt = null,
        private readonly ?string $updatedAt = null,
        string $code = ''
    ) {
        $this->name = $this->validateName($name);
        $this->status = $this->validateStatus($status);
        $this->code = $this->validateCode($code);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int) $data['id'] : null,
            (string) ($data['nome'] ?? ''),
            isset($data['descricao']) ? (string) $data['descricao'] : null,
            (bool) ($data['protegido'] ?? false),
            (string) ($data['status'] ?? 'ativo'),
            isset($data['criado_em']) ? (string) $data['criado_em'] : null,
            isset($data['atualizado_em']) ? (string) $data['atualizado_em'] : null,
            (string) ($data['codigo'] ?? '')
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): string
    {
        return $this->code;
    }

    private function validateCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') return '';
        if (strlen($code) > 80 || preg_match('/^[a-z0-9_-]+$/', $code) !== 1) {
            throw new InvalidArgumentException('Código técnico do perfil inválido.');
        }
        return $code;
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

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    private function validateName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Nome do perfil e obrigatorio.');
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('Nome do perfil excede 100 caracteres.');
        }

        return $name;
    }

    private function validateStatus(string $status): string
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Status de perfil invalido.');
        }

        return $status;
    }
}
