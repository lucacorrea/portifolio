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
        private readonly ?string $cest,
        private readonly ?int $origin,
        private readonly ?string $defaultCfop,
        private readonly ?string $icmsCst,
        private readonly ?string $csosn,
        private readonly ?string $pisCst,
        private readonly ?string $cofinsCst,
        private readonly ?string $icmsRate,
        private readonly ?string $pisRate,
        private readonly ?string $cofinsRate,
        private readonly ?string $taxGtin,
        private readonly ?string $taxUnit,
        private readonly ?string $ibsCbsCst,
        private readonly ?string $ibsCbsClassification,

        private readonly ?string $barcode,
        private readonly string $costPrice,
        private readonly string $salePrice,
        private readonly string $stock,
        private readonly string $minimumStock,
        private readonly ?string $location,
        private readonly string $status
    ) {
    }

    public static function fromArray(
        array $data
    ): self {
        return new self(
            name: self::text(
                (string) ($data['name'] ?? ''),
                'nome',
                150,
                true
            ),

            description: self::longText(
                $data['description'] ?? null
            ),

            category: self::text(
                (string) ($data['category'] ?? ''),
                'categoria',
                100,
                false
            ),

            manufacturer: self::text(
                (string) ($data['manufacturer'] ?? ''),
                'fabricante',
                100,
                false
            ),

            unit: self::normalizeUnit(
                $data['unit'] ?? 'UN'
            ),

            ncm: self::digits(
                $data['ncm'] ?? null,
                8,
                'NCM'
            ),

            cest: self::digits(
                $data['cest'] ?? null,
                7,
                'CEST'
            ),

            origin: self::origin(
                $data['origin'] ?? null
            ),

            defaultCfop: self::digits(
                $data['default_cfop'] ?? null,
                4,
                'CFOP'
            ),

            icmsCst: self::digits(
                $data['icms_cst'] ?? null,
                3,
                'CST ICMS'
            ),

            csosn: self::digits(
                $data['csosn'] ?? null,
                3,
                'CSOSN'
            ),

            pisCst: self::digits(
                $data['pis_cst'] ?? null,
                2,
                'CST PIS'
            ),

            cofinsCst: self::digits(
                $data['cofins_cst'] ?? null,
                2,
                'CST COFINS'
            ),

            icmsRate: self::nullableDecimal(
                $data['icms_rate'] ?? null,
                4,
                'alíquota ICMS'
            ),

            pisRate: self::nullableDecimal(
                $data['pis_rate'] ?? null,
                4,
                'alíquota PIS'
            ),

            cofinsRate: self::nullableDecimal(
                $data['cofins_rate'] ?? null,
                4,
                'alíquota COFINS'
            ),

            taxGtin: self::normalizeGtin(
                $data['tax_gtin'] ?? null
            ),

            taxUnit: self::optionalUnit(
                $data['tax_unit'] ?? null
            ),

            ibsCbsCst: self::digits(
                $data['ibs_cbs_cst'] ?? null,
                3,
                'CST IBS/CBS'
            ),

            ibsCbsClassification: self::digits(
                $data['ibs_cbs_classification'] ?? null,
                6,
                'classificação tributária IBS/CBS'
            ),

            barcode: self::text(
                (string) ($data['barcode'] ?? ''),
                'código de barras',
                100,
                false
            ),

            costPrice: self::decimal(
                $data['cost_price'] ?? '0',
                2,
                'preço de custo'
            ),

            salePrice: self::decimal(
                $data['sale_price'] ?? '0',
                2,
                'preço de venda'
            ),

            stock: self::decimal(
                $data['stock'] ?? '0',
                3,
                'estoque'
            ),

            minimumStock: self::decimal(
                $data['minimum_stock'] ?? '0',
                3,
                'estoque mínimo'
            ),

            location: self::text(
                (string) ($data['location'] ?? ''),
                'localização',
                100,
                false
            ),

            status: self::normalizeStatus(
                (string) ($data['status'] ?? 'ativo')
            )
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function manufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function ncm(): ?string
    {
        return $this->ncm;
    }

    public function cest(): ?string
    {
        return $this->cest;
    }

    public function origin(): ?int
    {
        return $this->origin;
    }

    public function defaultCfop(): ?string
    {
        return $this->defaultCfop;
    }

    public function icmsCst(): ?string
    {
        return $this->icmsCst;
    }

    public function csosn(): ?string
    {
        return $this->csosn;
    }

    public function pisCst(): ?string
    {
        return $this->pisCst;
    }

    public function cofinsCst(): ?string
    {
        return $this->cofinsCst;
    }

    public function icmsRate(): ?string
    {
        return $this->icmsRate;
    }

    public function pisRate(): ?string
    {
        return $this->pisRate;
    }

    public function cofinsRate(): ?string
    {
        return $this->cofinsRate;
    }

    public function taxGtin(): ?string
    {
        return $this->taxGtin;
    }

    public function taxUnit(): ?string
    {
        return $this->taxUnit;
    }

    public function ibsCbsCst(): ?string
    {
        return $this->ibsCbsCst;
    }

    public function ibsCbsClassification(): ?string
    {
        return $this->ibsCbsClassification;
    }

    public function barcode(): ?string
    {
        return $this->barcode;
    }

    public function costPrice(): string
    {
        return $this->costPrice;
    }

    public function salePrice(): string
    {
        return $this->salePrice;
    }

    public function stock(): string
    {
        return $this->stock;
    }

    public function minimumStock(): string
    {
        return $this->minimumStock;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function withPrices(
        string $costPrice,
        string $salePrice
    ): self {
        return new self(
            $this->name,
            $this->description,
            $this->category,
            $this->manufacturer,
            $this->unit,

            $this->ncm,
            $this->cest,
            $this->origin,
            $this->defaultCfop,
            $this->icmsCst,
            $this->csosn,
            $this->pisCst,
            $this->cofinsCst,
            $this->icmsRate,
            $this->pisRate,
            $this->cofinsRate,
            $this->taxGtin,
            $this->taxUnit,
            $this->ibsCbsCst,
            $this->ibsCbsClassification,

            $this->barcode,

            self::decimal(
                $costPrice,
                2,
                'preço de custo'
            ),

            self::decimal(
                $salePrice,
                2,
                'preço de venda'
            ),

            $this->stock,
            $this->minimumStock,
            $this->location,
            $this->status
        );
    }

    private static function digits(
        mixed $value,
        int $length,
        string $field
    ): ?string {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return null;
        }

        if (
            preg_match(
                '/^\d{' . $length . '}$/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s deve possuir exatamente %d dígitos.',
                    $field,
                    $length
                )
            );
        }

        return $value;
    }

    private static function origin(
        mixed $value
    ): ?int {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return null;
        }

        if (
            preg_match('/^[0-8]$/', $value)
            !== 1
        ) {
            throw new InvalidArgumentException(
                'Origem da mercadoria inválida.'
            );
        }

        return (int) $value;
    }

    private static function normalizeUnit(
        mixed $value
    ): string {
        $unit = strtoupper(
            trim((string) $value)
        );

        if ($unit === '') {
            throw new InvalidArgumentException(
                'Informe a unidade.'
            );
        }

        if (
            strlen($unit) > 20
            || str_contains($unit, "\0")
            || $unit !== strip_tags($unit)
        ) {
            throw new InvalidArgumentException(
                'Unidade inválida.'
            );
        }

        return $unit;
    }

    private static function optionalUnit(
        mixed $value
    ): ?string {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return null;
        }

        return self::normalizeUnit($value);
    }

    private static function normalizeGtin(
        mixed $value
    ): ?string {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return null;
        }

        if (
            preg_match('/^\d{1,14}$/', $value)
            !== 1
        ) {
            throw new InvalidArgumentException(
                'GTIN tributável inválido.'
            );
        }

        return $value;
    }

    private static function text(
        string $value,
        string $field,
        int $max,
        bool $required
    ): ?string {
        if (
            $value !== strip_tags($value)
            || str_contains($value, "\0")
        ) {
            throw new InvalidArgumentException(
                'Campo ' . $field . ' inválido.'
            );
        }

        $value = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? ''
        );

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException(
                    'Informe o ' . $field . '.'
                );
            }

            return null;
        }

        if (strlen($value) > $max) {
            throw new InvalidArgumentException(
                'Campo '
                . $field
                . ' excede '
                . $max
                . ' caracteres.'
            );
        }

        return $value;
    }

    private static function longText(
        mixed $value
    ): ?string {
        $value = trim(
            (string) ($value ?? '')
        );

        if ($value === '') {
            return null;
        }

        if (
            str_contains($value, "\0")
            || $value !== strip_tags($value)
        ) {
            throw new InvalidArgumentException(
                'Descrição inválida.'
            );
        }

        if (strlen($value) > 5000) {
            throw new InvalidArgumentException(
                'Descrição excede o limite permitido.'
            );
        }

        return $value;
    }

    private static function decimal(
        mixed $value,
        int $scale,
        string $field
    ): string {
        $normalized =
            self::normalizeDecimalText(
                $value,
                $field
            );

        $number = (float) $normalized;

        if ($number < 0) {
            throw new InvalidArgumentException(
                'O campo '
                . $field
                . ' não pode ser negativo.'
            );
        }

        return number_format(
            $number,
            $scale,
            '.',
            ''
        );
    }

    private static function nullableDecimal(
        mixed $value,
        int $scale,
        string $field
    ): ?string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        return self::decimal(
            $value,
            $scale,
            $field
        );
    }

    private static function normalizeDecimalText(
        mixed $value,
        string $field
    ): string {
        $value = trim((string) $value);

        $value = str_replace(
            ' ',
            '',
            $value
        );

        if ($value === '') {
            $value = '0';
        }

        if (str_contains($value, ',')) {
            $value = str_replace(
                '.',
                '',
                $value
            );

            $value = str_replace(
                ',',
                '.',
                $value
            );
        }

        if (
            preg_match(
                '/^\d+(?:\.\d+)?$/',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Informe um valor válido para '
                . $field
                . '.'
            );
        }

        return $value;
    }

    private static function normalizeStatus(
        string $status
    ): string {
        if (
            !in_array(
                $status,
                [
                    'ativo',
                    'inativo',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Status inválido.'
            );
        }

        return $status;
    }
}