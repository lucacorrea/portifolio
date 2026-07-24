<?php

declare(strict_types=1);

namespace App\Sales\DTO;

use InvalidArgumentException;

final class BudgetItemData
{
    public function __construct(
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

    public static function fromArray(array $data, int $order = 0): self
    {
        $type = (string) ($data['type'] ?? $data['tipo'] ?? '');
        if (!in_array($type, ['servico', 'produto', 'outro'], true)) {
            throw new InvalidArgumentException('Tipo de item inválido.');
        }

        $referenceId = filter_var($data['reference_id'] ?? $data['referencia_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $referenceId = is_int($referenceId) ? $referenceId : null;

        if ($type !== 'outro' && $referenceId === null) {
            throw new InvalidArgumentException('Selecione a referência do item.');
        }

        if ($type === 'outro') {
            $referenceId = null;
        }

        $quantity = self::decimal($data['quantity'] ?? $data['quantidade'] ?? '1', 3, 'quantidade');
        if ((float) $quantity <= 0.0) {
            throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
        }

        $unitPrice = self::decimal($data['unit_price'] ?? $data['valor_unitario'] ?? '0', 2, 'valor unitário');
        $discount = self::decimal($data['discount'] ?? $data['desconto'] ?? '0', 2, 'desconto');
        $gross = (float) $quantity * (float) $unitPrice;
        if ((float) $discount > $gross) {
            throw new InvalidArgumentException('O desconto do item não pode ultrapassar o subtotal bruto.');
        }

        return new self(
            type: $type,
            referenceId: $referenceId,
            description: self::text((string) ($data['description'] ?? $data['descricao'] ?? ''), 'descrição', 255, true),
            unit: self::text((string) ($data['unit'] ?? $data['unidade'] ?? 'un'), 'unidade', 20, true),
            quantity: $quantity,
            unitPrice: $unitPrice,
            discount: $discount,
            subtotal: number_format($gross - (float) $discount, 2, '.', ''),
            order: $order
        );
    }

    public function type(): string { return $this->type; }
    public function referenceId(): ?int { return $this->referenceId; }
    public function description(): string { return $this->description; }
    public function unit(): string { return $this->unit; }
    public function quantity(): string { return $this->quantity; }
    public function unitPrice(): string { return $this->unitPrice; }
    public function discount(): string { return $this->discount; }
    public function subtotal(): string { return $this->subtotal; }
    public function order(): int { return $this->order; }

    private static function text(string $value, string $field, int $max, bool $required): ?string
    {
        if ($value !== strip_tags($value) || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Campo ' . $field . ' inválido.');
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            if ($required) throw new InvalidArgumentException('Informe a ' . $field . '.');
            return null;
        }
        if (mb_strlen($value) > $max) throw new InvalidArgumentException('Campo ' . $field . ' excede ' . $max . ' caracteres.');
        return $value;
    }

    private static function decimal(mixed $value, int $scale, string $field): string
    {
        $value = str_replace(' ', '', trim((string) $value));
        if ($value === '') $value = '0';
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Informe um valor válido para ' . $field . '.');
        }
        $number = (float) $value;
        if ($number < 0) throw new InvalidArgumentException('O campo ' . $field . ' não pode ser negativo.');
        return number_format($number, $scale, '.', '');
    }
}
