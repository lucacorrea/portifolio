<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use InvalidArgumentException;

final class ServiceDefinition
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $code,
        private readonly string $name,
        private readonly ?string $category,
        private readonly ?string $compatibleEquipment,
        private readonly int $durationMinutes,
        private readonly string $value,
        private readonly ?string $description,
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0 || $this->name === '') {
            throw new InvalidArgumentException('Serviço inválido.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: isset($data['codigo']) ? (string) $data['codigo'] : null,
            name: (string) ($data['nome'] ?? ''),
            category: isset($data['categoria']) ? (string) $data['categoria'] : null,
            compatibleEquipment: isset($data['equipamentos_compativeis']) ? (string) $data['equipamentos_compativeis'] : null,
            durationMinutes: (int) ($data['duracao_minutos'] ?? 0),
            value: (string) ($data['valor'] ?? '0.00'),
            description: isset($data['descricao']) ? (string) $data['descricao'] : null,
            status: (string) ($data['status'] ?? 'ativo'),
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int { return $this->id; }
    public function code(): ?string { return $this->code; }
    public function displayCode(): string { return $this->code ?? sprintf('SRV-%06d', $this->id); }
    public function name(): string { return $this->name; }
    public function category(): ?string { return $this->category; }
    public function compatibleEquipment(): ?string { return $this->compatibleEquipment; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function value(): string { return $this->value; }
    public function description(): ?string { return $this->description; }
    public function status(): string { return $this->status; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}
