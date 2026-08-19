<?php

declare(strict_types=1);

namespace App\Fiscal\Tax;

use DateTimeImmutable;
use InvalidArgumentException;

final class IbsCbsApplicabilityResolver
{
    public const RULE_VERSION = 'RTC_2026_COMPAT_AM_V2';

    public const SOURCE =
        'IT 2025.002 v1.60; Ato Conjunto RFB/CGIBS 4/2026; '
        . 'compatibilidade técnica do ambiente de homologação SEFAZ/AM';

    private const REGULAR_REGIME_FROM = '2026-08-03';

    private const SIMPLES_PRODUCTION_FROM = '2027-01-01';

    private const AM_HOMOLOGATION_COMPAT_FROM = '2026-08-03';

    /**
     * Resolve quando o XML deve carregar o grupo IBS/CBS.
     *
     * A regra de homologação AM é deliberadamente mais estrita que o
     * cronograma de produção do Simples Nacional, porque o autorizador de
     * homologação do Amazonas efetivamente aplica a rejeição 1115 quando
     * o grupo IBS/CBS é omitido.
     *
     * @return array{
     *     required:bool,
     *     rule_version:string,
     *     source:string,
     *     effective_from:?string,
     *     reason:string
     * }
     */
    public function resolve(
        string $issueDate,
        int $crt,
        string $model,
        string $environment,
        string $uf = ''
    ): array {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $issueDate
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof DateTimeImmutable
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
        ) {
            throw new InvalidArgumentException(
                'Data de emissão inválida para resolver IBS/CBS.'
            );
        }

        if (!in_array($crt, [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException(
                'CRT inválido para resolver IBS/CBS.'
            );
        }

        if (!in_array($model, ['55', '65'], true)) {
            throw new InvalidArgumentException(
                'Modelo inválido para resolver IBS/CBS.'
            );
        }

        if (
            !in_array(
                $environment,
                ['homologacao', 'producao'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Ambiente inválido para resolver IBS/CBS.'
            );
        }

        $uf = strtoupper(trim($uf));

        if (
            $uf !== ''
            && preg_match('/^[A-Z]{2}$/', $uf) !== 1
        ) {
            throw new InvalidArgumentException(
                'UF inválida para resolver IBS/CBS.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Compatibilidade técnica - Homologação Amazonas
        |--------------------------------------------------------------------------
        |
        | Não antecipa a obrigação legal de produção do Simples Nacional.
        | Serve apenas para que o XML de homologação seja aceito pelo
        | autorizador AM que está aplicando a validação IBS/CBS.
        |
        */
        if (
            $environment === 'homologacao'
            && $uf === 'AM'
            && $issueDate >= self::AM_HOMOLOGATION_COMPAT_FROM
        ) {
            return $this->decision(
                true,
                self::AM_HOMOLOGATION_COMPAT_FROM,
                'Compatibilidade técnica de homologação SEFAZ/AM: '
                . 'gerar IBS/CBS para evitar rejeição 1115.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Regime regular
        |--------------------------------------------------------------------------
        */
        if ($crt === 3) {
            $required =
                $issueDate
                >= self::REGULAR_REGIME_FROM;

            return $this->decision(
                $required,
                self::REGULAR_REGIME_FROM,
                $required
                    ? 'IBS/CBS aplicável ao regime regular nesta vigência.'
                    : 'IBS/CBS ainda não aplicável ao regime regular nesta data.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CRT 1, 2 e 4 em produção
        |--------------------------------------------------------------------------
        */
        $required =
            $issueDate
            >= self::SIMPLES_PRODUCTION_FROM;

        return $this->decision(
            $required,
            self::SIMPLES_PRODUCTION_FROM,
            $required
                ? 'IBS/CBS aplicável ao optante do Simples Nacional nesta vigência.'
                : 'Produção para CRT 1, 2 ou 4 permanece no cronograma de 01/01/2027.'
        );
    }

    /**
     * @param array<string,mixed> $rule
     */
    public function assertCatalogRuleSupports(
        array $rule,
        string $model
    ): void {
        if (
            ($rule['calculation_mode'] ?? '')
            !== 'standard'
        ) {
            throw new InvalidArgumentException(
                'Esta classificação IBS/CBS exige cálculo ainda não implementado.'
            );
        }

        $indicators =
            $rule['indicators']
            ?? [];

        if (is_string($indicators)) {
            $decoded =
                json_decode(
                    $indicators,
                    true
                );

            $indicators =
                is_array($decoded)
                    ? $decoded
                    : [];
        }

        if (
            !is_array($indicators)
            || !$this->indicatorEnabled(
                $indicators,
                'ind_gIBSCBS'
            )
        ) {
            throw new InvalidArgumentException(
                'A classificação tributária não permite o grupo gIBSCBS padrão.'
            );
        }

        $modelIndicator =
            $model === '55'
                ? 'indNFe'
                : 'indNFCe';

        if (
            !$this->indicatorEnabled(
                $indicators,
                $modelIndicator
            )
        ) {
            throw new InvalidArgumentException(
                'A classificação tributária IBS/CBS não é permitida para o modelo fiscal selecionado.'
            );
        }
    }

    /**
     * @return array{
     *     required:bool,
     *     rule_version:string,
     *     source:string,
     *     effective_from:?string,
     *     reason:string
     * }
     */
    private function decision(
        bool $required,
        ?string $effectiveFrom,
        string $reason
    ): array {
        return [
            'required' =>
                $required,

            'rule_version' =>
                self::RULE_VERSION,

            'source' =>
                self::SOURCE,

            'effective_from' =>
                $effectiveFrom,

            'reason' =>
                $reason,
        ];
    }

    /**
     * @param array<string,mixed> $indicators
     */
    private function indicatorEnabled(
        array $indicators,
        string $name
    ): bool {
        $value =
            $indicators[$name]
            ?? $indicators[strtolower($name)]
            ?? null;

        return
            $value === true
            || $value === 1
            || $value === '1';
    }
}
