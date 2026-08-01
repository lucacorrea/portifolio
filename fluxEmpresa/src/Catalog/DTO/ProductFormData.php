<?php

declare(strict_types=1);

namespace App\Catalog\DTO;

use InvalidArgumentException;

final class ProductFormData
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $description,
        private readonly ?string $category,
        private readonly ?string $manufacturer,
        private readonly string $unit,
        private readonly ?string $ncm,
        private readonly ?string $barcode,
        private readonly string $costPrice,
        private readonly string $salePrice,
        private readonly string $stock,
        private readonly string $minimumStock,
        private readonly ?string $location,
        private readonly string $status
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: self::text((string) ($data['name'] ?? ''), 'nome', 150, true),
            description: self::longText($data['description'] ?? null),
            category: self::text((string) ($data['category'] ?? ''), 'categoria', 100, false),
            manufacturer: self::text((string) ($data['manufacturer'] ?? ''), 'fabricante', 100, false),
            unit: self::text((string) ($data['unit'] ?? 'un'), 'unidade', 20, true),
            ncm: self::normalizeNcm($data['ncm'] ?? ''),
            barcode: self::text((string) ($data['barcode'] ?? ''), 'código de barras', 100, false),
            costPrice: self::decimal($data['cost_price'] ?? '0', 2, 'preço de custo'),
            salePrice: self::decimal($data['sale_price'] ?? '0', 2, 'preço de venda'),
            stock: self::decimal($data['stock'] ?? '0', 3, 'estoque'),
            minimumStock: self::decimal($data['minimum_stock'] ?? '0', 3, 'estoque mínimo'),
            location: self::text((string) ($data['location'] ?? ''), 'localização', 100, false),
            status: self::normalizeStatus((string) ($data['status'] ?? 'ativo'))
        );
    }

    public function name(): string { return $this->name; }
    public function description(): ?string { return $this->description; }
    public function category(): ?string { return $this->category; }
    public function manufacturer(): ?string { return $this->manufacturer; }
    public function unit(): string { return $this->unit; }
    public function ncm(): ?string { return $this->ncm; }
    public function barcode(): ?string { return $this->barcode; }
    public function costPrice(): string { return $this->costPrice; }
    public function salePrice(): string { return $this->salePrice; }
    public function stock(): string { return $this->stock; }
    public function minimumStock(): string { return $this->minimumStock; }
    public function location(): ?string { return $this->location; }
    public function status(): string { return $this->status; }

    public function withPrices(string $costPrice, string $salePrice): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->category,
            $this->manufacturer,
            $this->unit,
            $this->ncm,
            $this->barcode,
            self::decimal($costPrice, 2, 'preço de custo'),
            self::decimal($salePrice, 2, 'preço de venda'),
            $this->stock,
            $this->minimumStock,
            $this->location,
            $this->status
        );
    }

    private static function normalizeNcm(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{1,8}$/', $value)) {
            throw new InvalidArgumentException('Informe um NCM com até 8 dígitos.');
        }
        return $value;
    }

    private static function text(string $value, string $field, int $max, bool $required): ?string
    {
        if ($value !== strip_tags($value) || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Campo ' . $field . ' inválido.');
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException('Informe o ' . $field . '.');
            }

            return null;
        }

        if (strlen($value) > $max) {
            throw new InvalidArgumentException('Campo ' . $field . ' excede ' . $max . ' caracteres.');
        }

        return $value;
    }

    private static function longText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('Descrição inválida.');
        }

        return $value;
    }

    private static function decimal(mixed $value, int $scale, string $field): string
    {
        $value = trim((string) $value);
        $value = str_replace(' ', '', $value);

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

        if ($number < 0) {
            throw new InvalidArgumentException('O campo ' . $field . ' não pode ser negativo.');
        }

        return number_format($number, $scale, '.', '');
    }

    private static function normalizeStatus(string $status): string
    {
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }

        return $status;
    }
}
