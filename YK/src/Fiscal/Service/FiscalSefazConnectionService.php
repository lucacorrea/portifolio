<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;
use DOMDocument;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Soap\SoapCurl;
use NFePHP\Common\Soap\SoapInterface;
use NFePHP\NFe\Tools;
use RuntimeException;
use Throwable;

final class FiscalSefazConnectionService
{
    public function __construct(
        private readonly FiscalConfigurationRepository $repository,
        private readonly FiscalSecretVault $vault,
        private readonly FiscalCertificateStorage $certificateStorage,
        private readonly FiscalRuntimeReadiness $runtimeReadiness
    ) {
    }

    /**
     * @return array{
     *     code:string,
     *     message:string,
     *     application_version:string,
     *     received_at:string
     * }
     */
    public function testHomologation(
        int $configurationId,
        int $userId
    ): array {
        return $this->test(
            $configurationId,
            $userId,
            false
        );
    }

    /**
     * Testa realmente o Status Serviço da SEFAZ.
     *
     * Não emite NF-e/NFC-e.
     *
     * @return array{
     *     code:string,
     *     message:string,
     *     application_version:string,
     *     received_at:string
     * }
     */
    public function test(
        int $configurationId,
        int $userId,
        bool $allowProduction = false
    ): array {
        if ($configurationId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException(
                'Configuração ou usuário fiscal inválido.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Requisitos do servidor
        |--------------------------------------------------------------------------
        */

        $runtime = $this->runtimeReadiness->inspect();

        if (!($runtime['homologation_ready'] ?? false)) {
            throw new InvalidArgumentException(
                'Conclua os requisitos técnicos do servidor antes do teste SEFAZ.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Perfil fiscal
        |--------------------------------------------------------------------------
        */

        $profile = $this->repository->connectionProfile(
            $configurationId
        );

        if ($profile === null) {
            throw new InvalidArgumentException(
                'Configuração fiscal não encontrada para o teste SEFAZ.'
            );
        }

        $environment = (string) ($profile['ambiente'] ?? '');
        $model = (string) ($profile['modelo'] ?? '');
        $uf = strtoupper(
            trim((string) ($profile['uf'] ?? ''))
        );

        if (
            !in_array(
                $environment,
                ['homologacao', 'producao'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Ambiente fiscal inválido.'
            );
        }

        if (!in_array($model, ['55', '65'], true)) {
            throw new InvalidArgumentException(
                'Modelo fiscal inválido.'
            );
        }

        if (!preg_match('/^[A-Z]{2}$/', $uf)) {
            throw new InvalidArgumentException(
                'UF da configuração fiscal inválida.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Produção
        |--------------------------------------------------------------------------
        */

        if (
            $environment === 'producao'
            && (
                !$allowProduction
                || !($runtime['production_allowed'] ?? false)
            )
        ) {
            throw new InvalidArgumentException(
                'O teste em produção exige permissão específica e o gate técnico liberado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Comunicação
        |--------------------------------------------------------------------------
        */

        $password = null;
        $pfx = null;

        try {
            /*
             * Confirma validade do registro do certificado.
             */
            $this->assertCertificateUsable($profile);

            /*
             * Para o STATUS SERVIÇO não precisamos do CSC.
             *
             * Isso é especialmente importante na NFC-e QR Code v3,
             * porque o CSC não participa desse processo.
             *
             * Portanto não abrimos o segredo CSC aqui.
             */
            $password = $this->openSecret(
                $profile,
                'senha',
                'certificado_chave_versao',
                'cifra_algoritmo'
            );

            /*
             * Localiza PFX/P12 no storage privado.
             */
            $certificatePath = $this->certificateStorage->resolve(
                (string) ($profile['arquivo_referencia'] ?? '')
            );

            if (
                $certificatePath === null
                || !is_file($certificatePath)
                || !is_readable($certificatePath)
            ) {
                throw new RuntimeException(
                    'Stored certificate file unavailable or unreadable.'
                );
            }

            $pfx = file_get_contents($certificatePath);

            if (!is_string($pfx) || $pfx === '') {
                throw new RuntimeException(
                    'Stored certificate unavailable.'
                );
            }

            /*
             * Valida efetivamente PFX + senha.
             */
            $certificate = Certificate::readPfx(
                $pfx,
                $password
            );

            /*
             * Confere se o CNPJ dentro do certificado corresponde
             * ao CNPJ esperado pelo perfil fiscal.
             */
            $this->assertCertificateIdentity(
                $certificate,
                $profile
            );

            /*
             * Monta Tools.
             *
             * CSC vazio de propósito:
             * Status Serviço e QR Code v3 não dependem do CSC.
             */
            $tools = new Tools(
                $this->configJson($profile),
                $certificate
            );

            /*
             * ESSENCIAL.
             *
             * 55 = NF-e
             * 65 = NFC-e
             *
             * Isso faz o NFePHP selecionar inclusive
             * wsnfe_4.00_mod65.xml para NFC-e.
             */
            $tools->model((int) $model);

            /*
             * Transporte SOAP/cURL controlado.
             */
            $this->configureSoapTransport(
                $tools,
                $certificate
            );

            /*
             * 1 = produção
             * 2 = homologação
             */
            $tpAmb = $environment === 'producao'
                ? 1
                : 2;

            /*
             * Chamada real.
             */
            $response = $tools->sefazStatus(
                $uf,
                $tpAmb,
                true
            );

            if (
                !is_string($response)
                || trim($response) === ''
            ) {
                throw new RuntimeException(
                    'SEFAZ returned an empty response.'
                );
            }
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | NÃO ESCONDER MAIS A CAUSA
            |--------------------------------------------------------------------------
            */

            [$technicalCode, $publicMessage] =
                $this->classifyTechnicalFailure(
                    $exception
                );

            /*
             * Salva um retorno seguro para a interface.
             *
             * Não salvamos senha, PFX, chave privada nem stack trace
             * no banco.
             */
            $this->repository->recordIntegrationTest(
                $configurationId,
                $environment,
                $model,
                $userId,
                false,
                $technicalCode,
                $publicMessage
            );

            /*
             * O LOG DO SERVIDOR recebe a exceção verdadeira.
             */
            error_log(
                sprintf(
                    '[Fiscal SEFAZ] config=%d ambiente=%s modelo=%s erro=%s',
                    $configurationId,
                    $environment,
                    $model,
                    $this->exceptionChainForLog($exception)
                )
            );

            throw new InvalidArgumentException(
                $publicMessage
            );
        } finally {
            /*
             * Limpeza dos dados sensíveis que ainda existirem
             * como strings em memória.
             */
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

        /*
        |--------------------------------------------------------------------------
        | Parser da resposta SEFAZ
        |--------------------------------------------------------------------------
        */

        try {
            $status = $this->parseStatus(
                $response,
                $environment === 'producao'
                    ? '1'
                    : '2'
            );
        } catch (InvalidArgumentException $exception) {
            $this->repository->recordIntegrationTest(
                $configurationId,
                $environment,
                $model,
                $userId,
                false,
                'resposta_invalida',
                'A SEFAZ retornou uma resposta inválida ou incompleta.'
            );

            error_log(
                '[Fiscal SEFAZ] resposta inválida: '
                . $this->safeLogMessage(
                    $exception->getMessage()
                )
            );

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | cStat
        |--------------------------------------------------------------------------
        */

        $success = $status['code'] === '107';

        $this->repository->recordIntegrationTest(
            $configurationId,
            $environment,
            $model,
            $userId,
            $success,
            $status['code'],
            $status['message']
        );

        if (!$success) {
            throw new InvalidArgumentException(
                'A SEFAZ respondeu '
                . $status['code']
                . ': '
                . $status['message']
                . '.'
            );
        }

        return $status;
    }

    /**
     * Configuração explícita do transporte.
     *
     * Evita deixar cURL negociar livremente HTTP/2/TLS
     * em provedores compartilhados.
     */
    private function configureSoapTransport(
        Tools $tools,
        Certificate $certificate
    ): void {
        /*
         * O NFePHP precisa escrever temporariamente
         * chave privada/certificado PEM para o cURL.
         */
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

        $soap = new SoapCurl($certificate);

        /*
         * Tempo suficiente para SEFAZ sem deixar request preso
         * indefinidamente.
         */
        $soap->timeout(30);

        /*
         * AMAZONAS / SEFAZ:
         *
         * Fixamos HTTP/1.1 para evitar negociação problemática
         * HTTP/2 em alguns ambientes cURL/proxy/hosting.
         */
        $soap->httpVersion('1.1');

        /*
         * Fixamos TLS 1.2.
         */
        $soap->protocol(
            SoapInterface::SSL_TLSV1_2
        );

        /*
         * Nunca:
         *
         * $soap->disableSecurity(true);
         * $soap->disableCertValidation(true);
         *
         * Não desligamos proteção para "fazer funcionar".
         */
        $soap->setDebugMode(false);

        /*
         * Injeta o transporte configurado no NFePHP.
         */
        $tools->loadSoapClass($soap);
    }

    /**
     * @param array<string,mixed> $profile
     */
    private function assertCertificateUsable(
        array $profile
    ): void {
        if (
            ($profile['certificado_status'] ?? '') !== 'ativo'
        ) {
            throw new RuntimeException(
                'Certificate inactive.'
            );
        }

        $validUntil = strtotime(
            (string) ($profile['valido_ate'] ?? '')
        );

        if (
            $validUntil === false
            || $validUntil <= time()
        ) {
            throw new RuntimeException(
                'Certificate expired.'
            );
        }
    }

    /**
     * Confere identidade do certificado aberto.
     *
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
            throw new RuntimeException(
                'Certificate does not contain a CNPJ.'
            );
        }

        if (
            $expectedCnpj !== ''
            && $certificateCnpj !== $expectedCnpj
        ) {
            throw new RuntimeException(
                'Certificate CNPJ does not match fiscal profile.'
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
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),

            'key_version' => (string) (
                $profile[$versionField]
                ?? ''
            ),

            'algorithm' => (string) (
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
    private function configJson(array $profile): string
    {
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
                'atualizacao' => date(DATE_ATOM),

                'tpAmb' =>
                    ($profile['ambiente'] ?? 'homologacao')
                    === 'producao'
                        ? 1
                        : 2,

                'razaosocial' => (string) (
                    $profile['razao_social']
                    ?? ''
                ),

                'cnpj' => $cnpj,

                'siglaUF' => strtoupper(
                    (string) (
                        $profile['uf']
                        ?? ''
                    )
                ),

                /*
                 * Mantido conforme seu projeto.
                 *
                 * PL_010_V1.30 existe no NFePHP 5.2.8.
                 */
                'schemes' => 'PL_010_V1.30',

                'versao' => (string) (
                    $profile['schema_versao']
                    ?? '4.00'
                ),

                'tokenIBPT' => null,

                /*
                 * NFC-e QR v3 não depende de CSC.
                 */
                'CSC' => null,
                'CSCid' => null,

                /*
                 * Este nome está correto para o schema
                 * de configuração do NFePHP 5.2.8.
                 */
                'aProxyConf' => null,
            ],
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @return array{
     *     code:string,
     *     message:string,
     *     application_version:string,
     *     received_at:string
     * }
     */
    private function parseStatus(
        string $xml,
        string $expectedEnvironment = '2'
    ): array {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);

        try {
            if (
                $xml === ''
                || !$dom->loadXML(
                    $xml,
                    LIBXML_NONET
                    | LIBXML_NOBLANKS
                )
            ) {
                throw new InvalidArgumentException(
                    'A SEFAZ retornou uma resposta inválida.'
                );
            }
        } finally {
            libxml_clear_errors();
        }

        $environment = $this->nodeValue(
            $dom,
            'tpAmb'
        );

        $code = $this->nodeValue(
            $dom,
            'cStat'
        );

        $message = $this->safeText(
            $this->nodeValue(
                $dom,
                'xMotivo'
            )
        );

        if ($environment !== $expectedEnvironment) {
            throw new InvalidArgumentException(
                'A SEFAZ retornou resposta de outro ambiente.'
            );
        }

        if (preg_match('/^\d{3}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                'A SEFAZ não retornou um cStat válido.'
            );
        }

        if ($message === '') {
            throw new InvalidArgumentException(
                'A SEFAZ retornou resposta sem xMotivo.'
            );
        }

        return [
            'code' => $code,

            'message' => $message,

            'application_version' =>
                $this->safeText(
                    $this->nodeValue(
                        $dom,
                        'verAplic'
                    )
                ),

            'received_at' =>
                $this->safeText(
                    $this->nodeValue(
                        $dom,
                        'dhRecbto'
                    )
                ),
        ];
    }

    /**
     * Classifica a exceção sem mandar detalhes
     * sensíveis para o usuário.
     *
     * @return array{0:string,1:string}
     */
    private function classifyTechnicalFailure(
        Throwable $exception
    ): array {
        $raw = $exception->getMessage();

        $message = function_exists('mb_strtolower')
            ? mb_strtolower($raw, 'UTF-8')
            : strtolower($raw);

        /*
         * PFX / certificado cliente.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'pkcs12',
                    'readpfx',
                    'mac verify',
                    'bad decrypt',
                    'private key',
                    'certificate cnpj does not match',
                    'certificate does not contain',
                    'certificate inactive',
                    'certificate expired',
                    'stored certificate',
                ]
            )
        ) {
            return [
                'certificado',
                'Não foi possível utilizar o certificado A1. '
                . 'Verifique o arquivo PFX/P12, a senha e a validade do certificado.',
            ];
        }

        /*
         * Vault.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'encrypted fiscal secret',
                    'decrypted fiscal secret',
                    'cipher',
                    'sodium',
                    'authentication tag',
                ]
            )
        ) {
            return [
                'credencial',
                'Não foi possível abrir a credencial criptografada do certificado fiscal.',
            ];
        }

        /*
         * Diretório temporário.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'temporary',
                    'unable to save temporary key files',
                    'permission denied',
                    'not writable',
                ]
            )
        ) {
            return [
                'filesystem',
                'O servidor não conseguiu criar os arquivos temporários seguros necessários à comunicação com a SEFAZ.',
            ];
        }

        /*
         * DNS.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'could not resolve',
                    'couldn\'t resolve',
                    'name or service not known',
                    'getaddrinfo',
                    'resolve host',
                ]
            )
        ) {
            return [
                'dns',
                'O servidor não conseguiu localizar o endereço da SEFAZ. Verifique DNS e conectividade externa.',
            ];
        }

        /*
         * Timeout.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'timed out',
                    'timeout',
                    'operation timed',
                ]
            )
        ) {
            return [
                'timeout',
                'A conexão com a SEFAZ excedeu o tempo limite.',
            ];
        }

        /*
         * SSL / TLS.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'ssl',
                    'tls',
                    'handshake',
                    'curl error 35',
                    'curl error 60',
                    'certificate verify',
                    'local issuer',
                    'wrong version number',
                ]
            )
        ) {
            return [
                'tls',
                'Falha TLS/SSL na comunicação com a SEFAZ. '
                . 'O servidor não conseguiu concluir o handshake seguro.',
            ];
        }

        /*
         * HTTP.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'http/2',
                    'http_1_1',
                    'http 500',
                    'http 403',
                    'http 404',
                    'http 502',
                    'http 503',
                ]
            )
        ) {
            return [
                'http',
                'A SEFAZ respondeu com falha HTTP durante a comunicação.',
            ];
        }

        /*
         * JSON / schema / configuração.
         */
        if (
            $this->containsAny(
                $message,
                [
                    'config',
                    'json',
                    'scheme',
                    'schema',
                ]
            )
        ) {
            return [
                'configuracao',
                'A configuração usada para inicializar a biblioteca fiscal é inválida ou incompatível.',
            ];
        }

        return [
            'erro_tecnico',
            'Não foi possível completar a comunicação com a SEFAZ. '
            . 'Consulte o log técnico do servidor para identificar a causa.',
        ];
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(
        string $haystack,
        array $needles
    ): bool {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function exceptionChainForLog(
        Throwable $exception
    ): string {
        $parts = [];
        $current = $exception;
        $limit = 0;

        while ($current !== null && $limit < 5) {
            $parts[] = sprintf(
                '%s(code=%s): %s @ %s:%d',
                get_class($current),
                (string) $current->getCode(),
                $this->safeLogMessage(
                    $current->getMessage()
                ),
                basename($current->getFile()),
                $current->getLine()
            );

            $current = $current->getPrevious();
            $limit++;
        }

        return implode(
            ' <- ',
            $parts
        );
    }

    private function safeLogMessage(
        string $value
    ): string {
        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            $value
        ) ?? '';

        return substr(
            trim($value),
            0,
            800
        );
    }

    private function nodeValue(
        DOMDocument $dom,
        string $name
    ): string {
        $node = $dom
            ->getElementsByTagName($name)
            ->item(0);

        return $node === null
            ? ''
            : trim(
                (string) $node->nodeValue
            );
    }

    private function safeText(
        string $value
    ): string {
        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            strip_tags($value)
        ) ?? '';

        return substr(
            trim($value),
            0,
            180
        );
    }
}