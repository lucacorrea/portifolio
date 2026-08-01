<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use InvalidArgumentException;

final class BudgetItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $budgetId,
        private readonly string $type,
        private readonly ?int $referenceId,
        private readonly string $description,
        private readonly string $unit,
        private readonly string $quantity,
        private readonly string $unitPrice,
        private readonly string $discount,
        private readonly string $subtotal,
        private readonly int $order
    ) {
        if ($this->id <= 0 || $this->budgetId <= 0 || $this->description === '') {
            throw new InvalidArgumentException('Item de orçamento inválido.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            budgetId: (int) ($data['orcamento_id'] ?? 0),
            type: (string) ($data['tipo'] ?? ''),
            referenceId: isset($data['referencia_id']) ? (int) $data['referencia_id'] : null,
            description: (string) ($data['descricao'] ?? ''),
            unit: (string) ($data['unidade'] ?? 'un'),
            quantity: (string) ($data['quantidade'] ?? '1.000'),
            unitPrice: (string) ($data['valor_unitario'] ?? '0.00'),
            discount: (string) ($data['desconto'] ?? '0.00'),
            subtotal: (string) ($data['subtotal'] ?? '0.00'),
            order: (int) ($data['ordem'] ?? 0)
        );
    }

    public function id(): int { return $this->id; }
    public function budgetId(): int { return $this->budgetId; }
    public function type(): string { return $this->type; }
    public function referenceId(): ?int { return $this->referenceId; }
    public function description(): string { return $this->description; }
    public function unit(): string { return $this->unit; }
    public function quantity(): string { return $this->quantity; }
    public function unitPrice(): string { return $this->unitPrice; }
    public function discount(): string { return $this->discount; }
    public function subtotal(): string { return $this->subtotal; }
    public function order(): int { return $this->order; }
}
