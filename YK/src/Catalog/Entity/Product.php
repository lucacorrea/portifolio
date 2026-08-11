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
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if (
            $this->id <= 0
            || $this->name === ''
        ) {
            throw new InvalidArgumentException(
                'Produto inválido.'
            );
        }
    }

    public static function fromArray(
        array $data
    ): self {
        return new self(
            id: (int) ($data['id'] ?? 0),

            code: isset($data['codigo'])
                ? (string) $data['codigo']
                : null,

            name: (string) (
                $data['nome']
                ?? ''
            ),

            description:
                isset($data['descricao'])
                    ? (string) $data['descricao']
                    : null,

            category:
                isset($data['categoria'])
                    ? (string) $data['categoria']
                    : null,

            manufacturer:
                isset($data['fabricante'])
                    ? (string) $data['fabricante']
                    : null,

            unit: (string) (
                $data['unidade']
                ?? 'UN'
            ),

            ncm: isset($data['ncm'])
                ? (string) $data['ncm']
                : null,

            cest: isset($data['cest'])
                ? (string) $data['cest']
                : null,

            origin:
                $data['origem_mercadoria'] === null
                    || !isset(
                        $data['origem_mercadoria']
                    )
                    ? null
                    : (int) $data['origem_mercadoria'],

            defaultCfop:
                isset($data['cfop_padrao'])
                    ? (string) $data['cfop_padrao']
                    : null,

            icmsCst:
                isset($data['cst_icms'])
                    ? (string) $data['cst_icms']
                    : null,

            csosn:
                isset($data['csosn'])
                    ? (string) $data['csosn']
                    : null,

            pisCst:
                isset($data['cst_pis'])
                    ? (string) $data['cst_pis']
                    : null,

            cofinsCst:
                isset($data['cst_cofins'])
                    ? (string) $data['cst_cofins']
                    : null,

            icmsRate:
                isset($data['aliquota_icms'])
                    ? (string) $data['aliquota_icms']
                    : null,

            pisRate:
                isset($data['aliquota_pis'])
                    ? (string) $data['aliquota_pis']
                    : null,

            cofinsRate:
                isset($data['aliquota_cofins'])
                    ? (string) $data['aliquota_cofins']
                    : null,

            taxGtin:
                isset($data['gtin_tributavel'])
                    ? (string) $data['gtin_tributavel']
                    : null,

            taxUnit:
                isset($data['unidade_tributavel'])
                    ? (string) $data['unidade_tributavel']
                    : null,

            ibsCbsCst:
                isset($data['cst_ibs_cbs'])
                    ? (string) $data['cst_ibs_cbs']
                    : null,

            ibsCbsClassification:
                isset(
                    $data[
                        'classificacao_tributaria_ibs_cbs'
                    ]
                )
                    ? (string) $data[
                        'classificacao_tributaria_ibs_cbs'
                    ]
                    : null,

            barcode:
                isset($data['codigo_barras'])
                    ? (string) $data['codigo_barras']
                    : null,

            costPrice: (string) (
                $data['preco_custo']
                ?? '0.00'
            ),

            salePrice: (string) (
                $data['preco_venda']
                ?? '0.00'
            ),

            stock: (string) (
                $data['estoque']
                ?? '0.000'
            ),

            minimumStock: (string) (
                $data['estoque_minimo']
                ?? '0.000'
            ),

            location:
                isset($data['localizacao'])
                    ? (string) $data['localizacao']
                    : null,

            status: (string) (
                $data['status']
                ?? 'ativo'
            ),

            createdAt: (string) (
                $data['criado_em']
                ?? ''
            ),

            updatedAt: (string) (
                $data['atualizado_em']
                ?? ''
            )
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
        return $this->code
            ?? sprintf(
                'PRD-%06d',
                $this->id
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

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }

    public function fiscalReady(
        int $crt
    ): bool {
        if (
            $this->ncm === null
            || strlen($this->ncm) !== 8
            || $this->origin === null
            || $this->defaultCfop === null
            || $this->pisCst === null
            || $this->cofinsCst === null
            || $this->taxUnit === null
        ) {
            return false;
        }

        if (
            in_array(
                $crt,
                [1, 2, 4],
                true
            )
        ) {
            return $this->csosn !== null;
        }

        return $this->icmsCst !== null;
    }

    public function stockSituation(): string
    {
        $stock = (float) $this->stock;
        $minimum =
            (float) $this->minimumStock;

        if ($stock <= 0.0) {
            return 'sem_estoque';
        }

        if ($stock <= $minimum) {
            return 'estoque_baixo';
        }

        return 'em_estoque';
    }

    public function unitProfit(): string
    {
        return number_format(
            (float) $this->salePrice
            - (float) $this->costPrice,
            2,
            '.',
            ''
        );
    }

    public function costMarginPercent(): ?string
    {
        $cost = (float) $this->costPrice;

        if ($cost <= 0.0) {
            return null;
        }

        return number_format(
            (
                (
                    (float) $this->salePrice
                    - $cost
                )
                / $cost
            ) * 100,
            2,
            '.',
            ''
        );
    }

    public function potentialStockProfit(): string
    {
        return number_format(
            (float) $this->unitProfit()
            * (float) $this->stock,
            2,
            '.',
            ''
        );
    }
}