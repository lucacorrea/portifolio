<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

final class ServiceOrderFiscalOrchestrator
{
    /** @param array<int,array<string,mixed>> $items @return array<string,mixed> */
    public function analyze(array $items): array
    {
        $productTotal = 0;
        $serviceTotal = 0;
        $missingProducts = [];
        $missingServices = [];
        $serviceGroups = [];
        foreach ($items as $item) {
            $type = (string) ($item['tipo'] ?? '');
            $cents = $this->cents((string) ($item['subtotal'] ?? '0'));
            if ($type === 'produto') {
                $productTotal += $cents;
                foreach (['ncm','cfop_padrao','cst_ibs_cbs','classificacao_tributaria_ibs_cbs'] as $field) {
                    if (trim((string) ($item[$field] ?? '')) === '') {
                        $missingProducts[] = (string) ($item['descricao'] ?? 'Produto') . ': ' . $field;
                    }
                }
                continue;
            }
            if ($type !== 'servico') {
                continue;
            }
            $serviceTotal += $cents;
            $required = ['codigo_tributacao_nacional','nbs','municipio_incidencia_ibge'];
            foreach ($required as $field) {
                if (trim((string) ($item[$field] ?? '')) === '') {
                    $missingServices[] = (string) ($item['descricao'] ?? 'Serviço') . ': ' . $field;
                }
            }
            $profile = implode('|', [
                $item['codigo_tributacao_nacional'] ?? '', $item['nbs'] ?? '',
                $item['municipio_incidencia_ibge'] ?? '', $item['aliquota_iss'] ?? '',
                $item['iss_retido'] ?? '0', $item['cst_ibs_cbs'] ?? '',
                $item['classificacao_tributaria_ibs_cbs'] ?? '', $item['cindop'] ?? '',
            ]);
            $hash = hash('sha256', $profile);
            $serviceGroups[$hash]['profile'] ??= $profile;
            $serviceGroups[$hash]['items'][] = $item;
            $serviceGroups[$hash]['total_cents'] = ($serviceGroups[$hash]['total_cents'] ?? 0) + $cents;
        }
        return [
            'product_total'=>$this->format($productTotal),
            'service_total'=>$this->format($serviceTotal),
            'available_product_document'=>$productTotal > 0 && $missingProducts === [],
            'available_service_documents'=>$missingServices === [] ? array_values($serviceGroups) : [],
            'missing_product_tax_data'=>array_values(array_unique($missingProducts)),
            'missing_service_tax_data'=>array_values(array_unique($missingServices)),
        ];
    }

    private function cents(string $value): int
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) return 0;
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
    }
    private function format(int $cents): string { return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT); }
}
