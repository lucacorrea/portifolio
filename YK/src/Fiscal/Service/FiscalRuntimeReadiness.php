<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

final class FiscalRuntimeReadiness
{
    /** @param array<string,bool> $capabilities */
    public function __construct(
        private readonly array $capabilities,
        private readonly bool $integrationEnabled,
        private readonly bool $productionEnabled,
        private readonly bool $masterKeyValid
    ) {
    }

    public static function fromRuntime(bool $integrationEnabled, bool $productionEnabled): self
    {
        $encodedKey = getenv('FISCAL_MASTER_KEY');
        $key = is_string($encodedKey) ? base64_decode(trim($encodedKey), true) : false;

        return new self([
            'openssl' => function_exists('openssl_pkcs12_read'),
            'curl' => extension_loaded('curl'),
            'dom' => class_exists('DOMDocument'),
            'simplexml' => function_exists('simplexml_load_string'),
            'soap' => class_exists('SoapClient'),
            'nfephp' => class_exists('NFePHP\\NFe\\Tools'),
        ], $integrationEnabled, $productionEnabled, is_string($key) && strlen($key) === 32);
    }

    /** @return array{homologation_ready:bool,production_allowed:bool,checks:array<int,array{key:string,label:string,ok:bool}>} */
    public function inspect(): array
    {
        $labels = [
            'openssl' => 'OpenSSL e leitura de certificado A1',
            'curl' => 'cURL para comunicação HTTPS',
            'dom' => 'DOM para assinatura e validação XML',
            'simplexml' => 'SimpleXML para respostas dos webservices',
            'soap' => 'SOAP para serviços da SEFAZ',
            'nfephp' => 'Biblioteca fiscal NFePHP/SPED-NFe',
        ];
        $checks = [];
        foreach ($labels as $key => $label) {
            $checks[] = ['key' => $key, 'label' => $label, 'ok' => ($this->capabilities[$key] ?? false) === true];
        }
        $checks[] = ['key' => 'master_key', 'label' => 'Chave mestra fiscal externa válida', 'ok' => $this->masterKeyValid];
        $checks[] = ['key' => 'integration_enabled', 'label' => 'Integração liberada no ambiente', 'ok' => $this->integrationEnabled];

        $ready = !in_array(false, array_column($checks, 'ok'), true);

        return [
            'homologation_ready' => $ready,
            'production_allowed' => $ready && $this->productionEnabled,
            'checks' => $checks,
        ];
    }
}
