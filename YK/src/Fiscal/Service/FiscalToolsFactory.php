<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Soap\SoapCurl;
use NFePHP\Common\Soap\SoapInterface;
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

    public function create(
        int $configurationId,
        string $model
    ): Tools {
        if ($configurationId <= 0) {
            throw new InvalidArgumentException(
                'Configuração fiscal inválida.'
            );
        }

        if (!in_array($model, ['55', '65'], true)) {
            throw new InvalidArgumentException(
                'Modelo fiscal inválido.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Perfil
        |--------------------------------------------------------------------------
        */

        $profile = $this->repository->connectionProfile(
            $configurationId
        );

        if (
            $profile === null
            || (string) ($profile['modelo'] ?? '') !== $model
        ) {
            throw new InvalidArgumentException(
                'Configuração fiscal do documento não encontrada.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Runtime
        |--------------------------------------------------------------------------
        */

        $readiness = $this->runtime->inspect();

        if (!($readiness['homologation_ready'] ?? false)) {
            throw new InvalidArgumentException(
                'O servidor ainda não possui os requisitos da integração fiscal.'
            );
        }

        $environment = (string) (
            $profile['ambiente']
            ?? ''
        );

        if (
            $environment === 'producao'
            && !($readiness['production_allowed'] ?? false)
        ) {
            throw new InvalidArgumentException(
                'A emissão fiscal em produção está bloqueada pelo servidor.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Certificado
        |--------------------------------------------------------------------------
        */

        if (
            ($profile['certificado_status'] ?? '')
            !== 'ativo'
        ) {
            throw new InvalidArgumentException(
                'O certificado A1 está inativo.'
            );
        }

        $validUntil = strtotime(
            (string) (
                $profile['valido_ate']
                ?? ''
            )
        );

        if (
            $validUntil === false
            || $validUntil <= time()
        ) {
            throw new InvalidArgumentException(
                'O certificado A1 está vencido.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | NFC-e QR Code
        |--------------------------------------------------------------------------
        */

        if ($model === '65') {
            $qrVersion = (string) (
                $profile['qr_code_versao']
                ?? ''
            );

            if (
                !in_array(
                    $qrVersion,
                    ['3', '300'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'NFC-e exige QR Code versão 3.'
                );
            }
        }

        $password = null;
        $pfx = null;

        try {
            /*
            |--------------------------------------------------------------------------
            | Senha A1
            |--------------------------------------------------------------------------
            */

            $password = $this->openSecret(
                $profile,
                'senha',
                'certificado_chave_versao',
                'cifra_algoritmo'
            );

            /*
            |--------------------------------------------------------------------------
            | PFX
            |--------------------------------------------------------------------------
            */

            $path = $this->certificateStorage->resolve(
                (string) (
                    $profile['arquivo_referencia']
                    ?? ''
                )
            );

            if (
                $path === null
                || !is_file($path)
                || !is_readable($path)
            ) {
                throw new RuntimeException(
                    'Stored certificate file unavailable or unreadable.'
                );
            }

            $pfx = file_get_contents($path);

            if (
                !is_string($pfx)
                || $pfx === ''
            ) {
                throw new RuntimeException(
                    'Stored certificate unavailable.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Certificate
            |--------------------------------------------------------------------------
            */

            $certificate = Certificate::readPfx(
                $pfx,
                $password
            );

            $this->assertCertificateIdentity(
                $certificate,
                $profile
            );

            /*
            |--------------------------------------------------------------------------
            | Tools
            |--------------------------------------------------------------------------
            |
            | QR Code v3 não necessita CSC.
            |
            | Portanto não abrimos mais CSC desnecessariamente
            | para criar a instância Tools.
            |
            */

            $tools = new Tools(
                $this->configJson($profile),
                $certificate
            );

            /*
             * Seleciona obrigatoriamente o modelo.
             */
            $tools->model(
                (int) $model
            );

            /*
             * NFC-e QR Code v3.
             */
            if ($model === '65') {
                $tools->forceQRCodeVersion('300');
            }

            /*
             * Mesmo transporte usado no teste SEFAZ.
             */
            $this->configureSoapTransport(
                $tools,
                $certificate
            );

            return $tools;
        } finally {
            if (
                is_string($password)
                && $password !== ''
                && function_exists('sodium_memzero')
            ) {
                sodium_memzero($password);
            }

            if (
                is_string($pfx)
                && $pfx !== ''
                && function_exists('sodium_memzero')
            ) {
                sodium_memzero($pfx);
            }
        }
    }

    /**
     * Transporte padrão de toda integração fiscal.
     */
    private function configureSoapTransport(
        Tools $tools,
        Certificate $certificate
    ): void {
        $temporaryDirectory = sys_get_temp_dir();

        if (
            $temporaryDirectory === ''
            || !is_dir($temporaryDirectory)
            || !is_writable($temporaryDirectory)
        ) {
            throw new RuntimeException(
                'PHP temporary directory is unavailable or not writable.'
            );
        }

        $soap = new SoapCurl(
            $certificate
        );

        /*
         * Timeout de conexão.
         */
        $soap->timeout(30);

        /*
         * Impede negociação automática HTTP/2
         * no servidor da hospedagem.
         */
        $soap->httpVersion('1.1');

        /*
         * SEFAZ / NFePHP via TLS 1.2.
         */
        $soap->protocol(
            SoapInterface::SSL_TLSV1_2
        );

        /*
         * Segurança não é desativada.
         */
        $soap->setDebugMode(false);

        /*
         * Injeta no Tools.
         */
        $tools->loadSoapClass(
            $soap
        );
    }

    /**
     * @param array<string,mixed> $profile
     */
    private function assertCertificateIdentity(
        Certificate $certificate,
        array $profile
    ): void {
        $certificateCnpj = preg_replace(
            '/\D+/',
            '',
            (string) $certificate->getCnpj()
        ) ?? '';

        $expectedCnpj = preg_replace(
            '/\D+/',
            '',
            (string) (
                $profile['titular_cnpj']
                ?? ''
            )
        ) ?? '';

        if ($certificateCnpj === '') {
            throw new InvalidArgumentException(
                'O certificado A1 não contém CNPJ identificável.'
            );
        }

        if (
            $expectedCnpj !== ''
            && $certificateCnpj !== $expectedCnpj
        ) {
            throw new InvalidArgumentException(
                'O CNPJ do certificado A1 não corresponde à configuração fiscal.'
            );
        }
    }

    /**
     * @param array<string,mixed> $profile
     */
    private function openSecret(
        array $profile,
        string $prefix,
        string $versionField,
        string $algorithmField
    ): string {
        $ciphertext =
            $profile[$prefix . '_ciphertext']
            ?? null;

        $nonce =
            $profile[$prefix . '_nonce']
            ?? null;

        $tag =
            $profile[$prefix . '_tag']
            ?? null;

        if (
            !is_string($ciphertext)
            || $ciphertext === ''
            || !is_string($nonce)
            || $nonce === ''
            || !is_string($tag)
            || $tag === ''
        ) {
            throw new RuntimeException(
                'Encrypted fiscal secret unavailable.'
            );
        }

        $secret = $this->vault->open([
            'ciphertext' =>
                base64_encode($ciphertext),

            'nonce' =>
                base64_encode($nonce),

            'tag' =>
                base64_encode($tag),

            'key_version' =>
                (string) (
                    $profile[$versionField]
                    ?? ''
                ),

            'algorithm' =>
                (string) (
                    $profile[$algorithmField]
                    ?? ''
                ),
        ]);

        if ($secret === '') {
            throw new RuntimeException(
                'Decrypted fiscal secret is empty.'
            );
        }

        return $secret;
    }

    /**
     * @param array<string,mixed> $profile
     */
    private function configJson(
        array $profile
    ): string {
        $cnpj = preg_replace(
            '/\D+/',
            '',
            (string) (
                $profile['titular_cnpj']
                ?? ''
            )
        ) ?? '';

        return json_encode(
            [
                'atualizacao' =>
                    date(DATE_ATOM),

                'tpAmb' =>
                    ($profile['ambiente'] ?? '')
                    === 'producao'
                        ? 1
                        : 2,

                'razaosocial' =>
                    (string) (
                        $profile['razao_social']
                        ?? ''
                    ),

                'cnpj' =>
                    $cnpj,

                'siglaUF' =>
                    strtoupper(
                        (string) (
                            $profile['uf']
                            ?? ''
                        )
                    ),

                'schemes' =>
                    'PL_010_V1.30',

                'versao' =>
                    (string) (
                        $profile['schema_versao']
                        ?? '4.00'
                    ),

                'tokenIBPT' =>
                    null,

                /*
                 * QR Code v3.
                 */
                'CSC' =>
                    null,

                'CSCid' =>
                    null,

                /*
                 * Nome esperado pelo NFePHP 5.2.8.
                 */
                'aProxyConf' =>
                    null,
            ],
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );
    }
}