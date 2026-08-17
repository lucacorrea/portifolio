<?php

declare(strict_types=1);

namespace App\Fiscal\Tax;

use InvalidArgumentException;

final class IbsCbsCalculation
{
    /** @param array<string,mixed> $item @return array<string,string> */
    public function calculate(array $item): array
    {
        $rule = $item['ibs_cbs_rule'] ?? null;
        if (!is_array($rule) || ($rule['calculation_mode'] ?? '') !== 'standard') {
            throw new InvalidArgumentException('Esta classificação IBS/CBS ainda exige regra tributária não implementada.');
        }
        $productBase = Decimal::moneyToCents((string) ($item['subtotal'] ?? ''));
        $icms = (string) ($item['cst_icms'] ?? '') === '00'
            ? Decimal::taxCents($productBase, Decimal::rateToUnits((string) ($item['aliquota_icms'] ?? '0')))
            : 0;
        $pis = Decimal::taxCents($productBase, Decimal::rateToUnits((string) ($item['aliquota_pis'] ?? '0')));
        $cofins = Decimal::taxCents($productBase, Decimal::rateToUnits((string) ($item['aliquota_cofins'] ?? '0')));
        $base = $productBase - $icms - $pis - $cofins;
        if ($base < 0) {
            throw new InvalidArgumentException('A base IBS/CBS calculada para o item ficou negativa.');
        }
        $ufRate = Decimal::rateToUnits((string) ($rule['ibs_uf_rate'] ?? ''));
        $cityRate = Decimal::rateToUnits((string) ($rule['ibs_city_rate'] ?? ''));
        $cbsRate = Decimal::rateToUnits((string) ($rule['cbs_rate'] ?? ''));
        $uf = Decimal::taxCents($base, $ufRate);
        $city = Decimal::taxCents($base, $cityRate);
        $cbs = Decimal::taxCents($base, $cbsRate);

        return [
            'base' => Decimal::formatCents($base),
            'ibs_uf_rate' => Decimal::formatRate($ufRate),
            'ibs_uf' => Decimal::formatCents($uf),
            'ibs_city_rate' => Decimal::formatRate($cityRate),
            'ibs_city' => Decimal::formatCents($city),
            'ibs' => Decimal::formatCents($uf + $city),
            'cbs_rate' => Decimal::formatRate($cbsRate),
            'cbs' => Decimal::formatCents($cbs),
        ];
    }
}
