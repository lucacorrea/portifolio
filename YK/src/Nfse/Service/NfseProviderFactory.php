<?php

declare(strict_types=1);

namespace App\Nfse\Service;

use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;
use App\Nfse\Provider\BethaFlyNfseProvider;
use App\Nfse\Transport\CurlMtlsTransport;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use RuntimeException;

final class NfseProviderFactory
{
    public function __construct(
        private readonly FiscalSecretVault $vault,
        private readonly FiscalCertificateStorage $certificateStorage
    ) {
    }

    /** @param array<string,mixed> $profile @return array{provider:BethaFlyNfseProvider,certificate:Certificate,schema_path:string} */
    public function create(array $profile): array
    {
        if (($profile['provedor'] ?? '') !== 'betha_fly' || ($profile['certificado_status'] ?? '') !== 'ativo'
            || strtotime((string)($profile['valido_ate'] ?? '')) <= time()
        ) {
            throw new InvalidArgumentException('Provedor ou certificado NFS-e inválido.');
        }
        $password = $this->vault->open([
            'ciphertext'=>base64_encode((string)$profile['senha_ciphertext']),
            'nonce'=>base64_encode((string)$profile['senha_nonce']),
            'tag'=>base64_encode((string)$profile['senha_tag']),
            'key_version'=>(string)$profile['chave_versao'],
            'algorithm'=>(string)$profile['cifra_algoritmo'],
        ]);
        $path = $this->certificateStorage->resolve((string)$profile['arquivo_referencia']);
        $contents = $path === null ? false : file_get_contents($path);
        if (!is_string($contents) || $contents === '') throw new RuntimeException('Certificado NFS-e armazenado indisponível.');
        try {
            $certificate = Certificate::readPfx($contents, $password);
            $transport = new CurlMtlsTransport((string)$profile['base_url'], (string)$path, $password);
            $password = '';
            return [
                'provider'=>new BethaFlyNfseProvider($transport),
                'certificate'=>$certificate,
                'schema_path'=>(string)$profile['schema_path'],
            ];
        } finally {
            if ($password !== '' && function_exists('sodium_memzero')) sodium_memzero($password);
        }
    }
}
