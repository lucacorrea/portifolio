<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use InvalidArgumentException;

final class Product
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $code,
        private readonly string $name,
        private readonly ?string $description,
        private readonly ?string $category,
        private readonly ?string $manufacturer,
        private readonly string $unit,
        private readonly ?string $barcode,
        private readonly string $costPrice,
        private readonly string $salePrice,
        private readonly string $stock,
        private readonly string $minimumStock,
        private readonly ?string $location,
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0 || $this->name === '') {
            throw new InvalidArgumentException('Produto inválido.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: isset($data['codigo']) ? (string) $data['codigo'] : null,
            name: (string) ($data['nome'] ?? ''),
            description: isset($data['descricao']) ? (string) $data['descricao'] : null,
            category: isset($data['categoria']) ? (string) $data['categoria'] : null,
            manufacturer: isset($data['fabricante']) ? (string) $data['fabricante'] : null,
            unit: (string) ($data['unidade'] ?? 'un'),
            barcode: isset($data['codigo_barras']) ? (string) $data['codigo_barras'] : null,
            costPrice: (string) ($data['preco_custo'] ?? '0.00'),
            salePrice: (string) ($data['preco_venda'] ?? '0.00'),
            stock: (string) ($data['estoque'] ?? '0.000'),
            minimumStock: (string) ($data['estoque_minimo'] ?? '0.000'),
            location: isset($data['localizacao']) ? (string) $data['localizacao'] : null,
            status: (string) ($data['status'] ?? 'ativo'),
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int { return $this->id; }
    public function code(): ?string { return $this->code; }
    public function displayCode(): string { return $this->code ?? sprintf('PRD-%06d', $this->id); }
    public function name(): string { return $this->name; }
    public function description(): ?string { return $this->description; }
    public function category(): ?string { return $this->category; }
    public function manufacturer(): ?string { return $this->manufacturer; }
    public function unit(): string { return $this->unit; }
    public function barcode(): ?string { return $this->barcode; }
    public function costPrice(): string { return $this->costPrice; }
    public function salePrice(): string { return $this->salePrice; }
    public function stock(): string { return $this->stock; }
    public function minimumStock(): string { return $this->minimumStock; }
    public function location(): ?string { return $this->location; }
    public function status(): string { return $this->status; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }

    public function stockSituation(): string
    {
        $stock = (float) $this->stock;
        $minimum = (float) $this->minimumStock;

        if ($stock <= 0.0) {
            return 'sem_estoque';
        }

        if ($stock <= $minimum) {
            return 'estoque_baixo';
        }

        return 'em_estoque';
    }
}
