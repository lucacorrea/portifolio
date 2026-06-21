<?php

declare(strict_types=1);

namespace App\ServiceOrder\Entity;

final class ServiceOrderItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
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
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (int) ($data['ordem_servico_id'] ?? 0),
            (string) ($data['tipo'] ?? 'outro'),
            isset($data['referencia_id']) ? (int) $data['referencia_id'] : null,
            (string) ($data['descricao'] ?? ''),
            (string) ($data['unidade'] ?? 'un'),
            (string) ($data['quantidade'] ?? '1.000'),
            (string) ($data['valor_unitario'] ?? '0.00'),
            (string) ($data['desconto'] ?? '0.00'),
            (string) ($data['subtotal'] ?? '0.00'),
            (int) ($data['ordem'] ?? 0)
        );
    }

    public function id(): int { return $this->id; }
    public function orderId(): int { return $this->orderId; }
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
