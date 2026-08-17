<?php

declare(strict_types=1);

namespace App\Fiscal\Tax;

use DateTimeImmutable;
use InvalidArgumentException;

final class IbsCbsApplicabilityResolver
{
    public const RULE_VERSION = 'NT_2025_002_V1_50';
    public const SOURCE = 'NT 2025.002 v1.50 e Ato Conjunto RFB/CGIBS 4/2026';

    /**
     * @return array{required:bool,rule_version:string,source:string,effective_from:?string,reason:string}
     */
    public function resolve(string $issueDate, int $crt, string $model, string $environment): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('Data de emissão inválida para resolver IBS/CBS.');
        }
        if (!in_array($crt, [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('CRT inválido para resolver IBS/CBS.');
        }
        if (!in_array($model, ['55', '65'], true)) {
            throw new InvalidArgumentException('Modelo inválido para resolver IBS/CBS.');
        }
        if (!in_array($environment, ['homologacao', 'producao'], true)) {
            throw new InvalidArgumentException('Ambiente inválido para resolver IBS/CBS.');
        }

        if ($crt === 3) {
            $effectiveFrom = $environment === 'homologacao' ? '2026-07-01' : '2026-08-03';
            $required = $issueDate >= $effectiveFrom;
            return $this->decision(
                $required,
                $effectiveFrom,
                $required
                    ? 'IBS/CBS obrigatório para CRT 3 neste ambiente e vigência.'
                    : 'IBS/CBS ainda não obrigatório para CRT 3 nesta data e ambiente.'
            );
        }

        $effectiveFrom = '2027-01-01';
        $required = $issueDate >= $effectiveFrom;
        return $this->decision(
            $required,
            $effectiveFrom,
            $required
                ? 'IBS/CBS obrigatório para optante do Simples Nacional nesta vigência.'
                : 'IBS/CBS não obrigatório para CRT 1, 2 ou 4 antes de 2027.'
        );
    }

    /**
     * @param array<string,mixed> $rule
     */
    public function assertCatalogRuleSupports(array $rule, string $model): void
    {
        if (($rule['calculation_mode'] ?? '') !== 'standard') {
            throw new InvalidArgumentException('Esta classificação IBS/CBS exige cálculo ainda não implementado.');
        }
        $indicators = $rule['indicators'] ?? [];
        if (is_string($indicators)) {
            $decoded = json_decode($indicators, true);
            $indicators = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($indicators) || !$this->indicatorEnabled($indicators, 'ind_gIBSCBS')) {
            throw new InvalidArgumentException('A classificação tributária não permite o grupo gIBSCBS padrão.');
        }
        $modelIndicator = $model === '55' ? 'indNFe' : 'indNFCe';
        if (!$this->indicatorEnabled($indicators, $modelIndicator)) {
            throw new InvalidArgumentException('A classificação tributária não é permitida para o modelo fiscal selecionado.');
        }
    }

    /** @return array{required:bool,rule_version:string,source:string,effective_from:?string,reason:string} */
    private function decision(bool $required, ?string $effectiveFrom, string $reason): array
    {
        return [
            'required' => $required,
            'rule_version' => self::RULE_VERSION,
            'source' => self::SOURCE,
            'effective_from' => $effectiveFrom,
            'reason' => $reason,
        ];
    }

    /** @param array<string,mixed> $indicators */
    private function indicatorEnabled(array $indicators, string $name): bool
    {
        $value = $indicators[$name] ?? $indicators[strtolower($name)] ?? null;
        return $value === true || $value === 1 || $value === '1';
    }
}
