<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use RuntimeException;

final class FiscalToolsFactory
{
    public function __construct(
        private readonly FiscalConfigurationRepository $repository,
        private readonly FiscalSecretVault $vault,
        private readonly FiscalCertificateStorage $certificateStorage,
        private readonly FiscalRuntimeReadiness $runtime
    ) {
    }

    public function create(int $configurationId, string $model): Tools
    {
        $profile = $this->repository->connectionProfile($configurationId);
        if ($profile === null || (string) $profile['modelo'] !== $model) {
            throw new InvalidArgumentException('Configuração fiscal do documento não encontrada.');
        }
        $readiness = $this->runtime->inspect();
        if (!$readiness['homologation_ready']) {
            throw new InvalidArgumentException('O servidor ainda não possui os requisitos da integração fiscal.');
        }
        if ($profile['ambiente'] === 'producao' && !$readiness['production_allowed']) {
            throw new InvalidArgumentException('A emissão fiscal em produção está bloqueada pelo servidor.');
        }
        if (($profile['certificado_status'] ?? '') !== 'ativo'
            || strtotime((string) ($profile['valido_ate'] ?? '')) <= time()
        ) {
            throw new InvalidArgumentException('O certificado A1 está inativo ou vencido.');
        }

        $password = $this->openSecret($profile, 'senha', 'certificado_chave_versao', 'cifra_algoritmo');
        $csc = $this->openOptionalSecret($profile, 'csc', 'csc_chave_versao', 'csc_algoritmo');
        try {
            $path = $this->certificateStorage->resolve((string) $profile['arquivo_referencia']);
            $pfx = $path === null ? false : file_get_contents($path);
            if (!is_string($pfx) || $pfx === '') {
                throw new RuntimeException('Stored certificate unavailable.');
            }
            $certificate = Certificate::readPfx($pfx, $password);
            $tools = new Tools($this->configJson($profile, $csc), $certificate);
            $tools->model((int) $model);
            if ($model === '65' && !empty($profile['qr_code_versao'])) {
                $qrVersion = (string) $profile['qr_code_versao'];
                $tools->forceQRCodeVersion(match ($qrVersion) {
                    '2', '200' => '200',
                    '3', '300' => '300',
                    default => throw new InvalidArgumentException('Versão do QR Code NFC-e não suportada.'),
                });
            }
            return $tools;
        } finally {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($password);
                if ($csc !== '') {
                    sodium_memzero($csc);
                }
            }
        }
    }

    /** @param array<string,mixed> $profile */
    private function openSecret(array $profile, string $prefix, string $versionField, string $algorithmField): string
    {
        foreach (['ciphertext', 'nonce', 'tag'] as $suffix) {
            if (!is_string($profile[$prefix . '_' . $suffix] ?? null)) {
                throw new RuntimeException('Encrypted fiscal secret unavailable.');
            }
        }
        return $this->vault->open([
            'ciphertext' => base64_encode($profile[$prefix . '_ciphertext']),
            'nonce' => base64_encode($profile[$prefix . '_nonce']),
            'tag' => base64_encode($profile[$prefix . '_tag']),
            'key_version' => (string) ($profile[$versionField] ?? ''),
            'algorithm' => (string) ($profile[$algorithmField] ?? ''),
        ]);
    }

    /** @param array<string,mixed> $profile */
    private function openOptionalSecret(array $profile, string $prefix, string $versionField, string $algorithmField): string
    {
        return empty($profile[$prefix . '_ciphertext'])
            ? ''
            : $this->openSecret($profile, $prefix, $versionField, $algorithmField);
    }

    /** @param array<string,mixed> $profile */
    private function configJson(array $profile, string $csc): string
    {
        return json_encode([
            'atualizacao' => date(DATE_ATOM),
            'tpAmb' => $profile['ambiente'] === 'producao' ? 1 : 2,
            'razaosocial' => (string) $profile['razao_social'],
            'cnpj' => preg_replace('/\D+/', '', (string) $profile['titular_cnpj']),
            'siglaUF' => (string) $profile['uf'],
            'schemes' => 'PL_010_V1.30',
            'versao' => (string) $profile['schema_versao'],
            'tokenIBPT' => null,
            'CSC' => $csc === '' ? null : $csc,
            'CSCid' => $profile['csc_id'] === null ? null : (string) $profile['csc_id'],
            'aProxyConf' => null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
