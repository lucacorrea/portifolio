<?php

declare(strict_types=1);

namespace App\ServiceOrder\Entity;

final class ServiceOrderItem
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
        private readonly string $type,
        private readonly string $origin,
        private readonly ?int $referenceId,
        private readonly ?int $budgetItemId,
        private readonly string $description,
        private readonly ?string $executionLocation,
        private readonly ?string $referenceName,
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
            (string) ($data['origem'] ?? 'manual'),
            isset($data['referencia_id']) ? (int) $data['referencia_id'] : null,
            isset($data['orcamento_item_id']) ? (int) $data['orcamento_item_id'] : null,
            (string) ($data['descricao'] ?? ''),
            self::nullableText($data['local_execucao'] ?? null),
            self::nullableText($data['referencia_nome'] ?? null),
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
    public function origin(): string { return $this->origin; }
    public function referenceId(): ?int { return $this->referenceId; }
    public function budgetItemId(): ?int { return $this->budgetItemId; }
    public function description(): string { return $this->description; }

    /**
     * Descrição pronta para apresentação. Em itens antigos, quando a descrição
     * era usada como local, recupera o nome do serviço pela referência.
     */
    public function displayDescription(): string
    {
        if (
            $this->type === 'servico'
            && $this->executionLocation === null
            && $this->referenceName !== null
            && trim($this->referenceName) !== ''
            && trim($this->description) !== trim($this->referenceName)
        ) {
            return $this->referenceName;
        }

        return $this->description;
    }

    /**
     * Local do serviço. Também interpreta registros legados onde o campo
     * descricao continha o ambiente (ex.: Recepção 2, Administração).
     */
    public function executionLocation(): ?string
    {
        if ($this->executionLocation !== null && trim($this->executionLocation) !== '') {
            return $this->executionLocation;
        }

        if (
            $this->type === 'servico'
            && $this->referenceName !== null
            && trim($this->referenceName) !== ''
            && trim($this->description) !== ''
            && trim($this->description) !== trim($this->referenceName)
        ) {
            return $this->description;
        }

        return null;
    }

    public function referenceName(): ?string { return $this->referenceName; }
    public function unit(): string { return $this->unit; }
    public function quantity(): string { return $this->quantity; }
    public function unitPrice(): string { return $this->unitPrice; }
    public function discount(): string { return $this->discount; }
    public function subtotal(): string { return $this->subtotal; }
    public function order(): int { return $this->order; }

    private static function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}

