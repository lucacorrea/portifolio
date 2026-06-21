<?php

declare(strict_types=1);

namespace App\Workforce\Entity;

use InvalidArgumentException;

final class Employee
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $code,
        private readonly string $name,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0) {
            throw new InvalidArgumentException(
                'ID de funcionário inválido.'
            );
        }

        if ($this->name === '') {
            throw new InvalidArgumentException(
                'Nome do funcionário é obrigatório.'
            );
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: isset($data['codigo'])
                ? (string) $data['codigo']
                : null,
            name: (string) ($data['nome'] ?? ''),
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function displayCode(): string
    {
        return $this->code ?? sprintf(
            'FUN-%06d',
            $this->id
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }
}
