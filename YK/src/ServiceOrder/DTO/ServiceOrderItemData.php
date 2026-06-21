<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use InvalidArgumentException;

final class ServiceOrderItemData
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

    public static function fromArray(array $data, int $index): self
    {
        $type = (string) ($data['type'] ?? $data['tipo'] ?? '');
        if (!in_array($type, ['servico', 'produto', 'outro'], true)) {
            throw new InvalidArgumentException('Tipo de item inválido.');
        }

        $referenceId = self::optionalPositiveInt($data['reference_id'] ?? $data['referencia_id'] ?? null);
        if ($type !== 'outro' && $referenceId === null) {
            throw new InvalidArgumentException('Selecione a referência do item.');
        }
        if ($type === 'outro') {
            $referenceId = null;
        }

        $description = self::simpleText($data['description'] ?? $data['descricao'] ?? '', 'descrição', 255);
        $unit = self::simpleText($data['unit'] ?? $data['unidade'] ?? 'un', 'unidade', 20);
        $quantity = self::decimal($data['quantity'] ?? $data['quantidade'] ?? '1', 'quantidade', false);
        $unitPrice = self::decimal($data['unit_price'] ?? $data['valor_unitario'] ?? '0', 'valor unitário', true);
        $discount = self::decimal($data['discount'] ?? $data['desconto'] ?? '0', 'desconto', true);
        $gross = (float) $quantity * (float) $unitPrice;
        if ((float) $discount > $gross) {
            throw new InvalidArgumentException('Desconto do item não pode superar o valor bruto.');
        }

        return new self(
            type: $type,
            referenceId: $referenceId,
            description: $description,
            unit: $unit,
            quantity: number_format((float) $quantity, 3, '.', ''),
            unitPrice: $unitPrice,
            discount: $discount,
            subtotal: number_format($gross - (float) $discount, 2, '.', ''),
            order: $index
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

    private static function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($int) ? $int : null;
    }

    private static function simpleText(mixed $value, string $field, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            throw new InvalidArgumentException('Informe ' . $field . '.');
        }
        if (str_contains($text, "\0") || $text !== strip_tags($text) || mb_strlen($text) > $maxLength) {
            throw new InvalidArgumentException('Informe ' . $field . ' válida.');
        }
        return $text;
    }

    private static function decimal(mixed $value, string $field, bool $allowZero): string
    {
        $value = str_replace(' ', '', trim((string) $value));
        if ($value === '') {
            $value = '0';
        }
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Informe um valor válido para ' . $field . '.');
        }
        $number = (float) $value;
        if ($number < 0 || (!$allowZero && $number <= 0)) {
            throw new InvalidArgumentException('Informe um valor válido para ' . $field . '.');
        }
        return number_format($number, 2, '.', '');
    }
}
