<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalConfigurationRepository;
use App\Fiscal\Security\FiscalSecretVault;
use App\Fiscal\Storage\FiscalCertificateStorage;
use DOMDocument;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
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

    /** @return array{code:string,message:string,application_version:string,received_at:string} */
    public function testHomologation(int $configurationId, int $userId): array
    {
        if ($configurationId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Configuração ou usuário fiscal inválido.');
        }
        if (!$this->runtimeReadiness->inspect()['homologation_ready']) {
            throw new InvalidArgumentException('Conclua os requisitos técnicos do servidor antes do teste SEFAZ.');
        }

        $profile = $this->repository->connectionProfile($configurationId);
        if ($profile === null || $profile['ambiente'] !== 'homologacao') {
            throw new InvalidArgumentException('O teste está disponível somente para configuração de homologação.');
        }

        $environment = (string) $profile['ambiente'];
        $model = (string) $profile['modelo'];
        try {
            $this->assertCertificateUsable($profile);
            $password = $this->openSecret($profile, 'senha', 'certificado_chave_versao', 'cifra_algoritmo');
            $csc = $this->openOptionalSecret($profile, 'csc', 'csc_chave_versao', 'csc_algoritmo');
            $certificatePath = $this->certificateStorage->resolve((string) $profile['arquivo_referencia']);
            $pfx = $certificatePath === null ? false : file_get_contents($certificatePath);
            if (!is_string($pfx) || $pfx === '') {
                throw new RuntimeException('Stored certificate unavailable.');
            }

            $certificate = Certificate::readPfx($pfx, $password);
            $tools = new Tools($this->configJson($profile, $csc), $certificate);
            $tools->model((int) $model);
            $response = $tools->sefazStatus((string) $profile['uf'], 2, true);
        } catch (Throwable $exception) {
            $this->repository->recordIntegrationTest(
                $configurationId,
                $environment,
                $model,
                $userId,
                false,
                'erro_tecnico',
                'Não foi possível completar a comunicação segura com a SEFAZ.'
            );
            error_log('Fiscal SEFAZ homologation test failed [' . get_class($exception) . '].');
            throw new InvalidArgumentException('Não foi possível comunicar com a SEFAZ em homologação. Verifique o certificado e tente novamente.');
        } finally {
            if (isset($password) && function_exists('sodium_memzero')) {
                sodium_memzero($password);
            }
            if (isset($csc) && $csc !== '' && function_exists('sodium_memzero')) {
                sodium_memzero($csc);
            }
        }

        try {
            $status = $this->parseStatus($response);
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
            throw $exception;
        }
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
                'A SEFAZ respondeu ' . $status['code'] . ': ' . $status['message'] . '.'
            );
        }

        return $status;
    }

    /** @param array<string,mixed> $profile */
    private function assertCertificateUsable(array $profile): void
    {
        if (($profile['certificado_status'] ?? '') !== 'ativo'
            || strtotime((string) ($profile['valido_ate'] ?? '')) <= time()
        ) {
            throw new RuntimeException('Certificate inactive or expired.');
        }
    }

    /** @param array<string,mixed> $profile */
    private function openSecret(array $profile, string $prefix, string $versionField, string $algorithmField): string
    {
        $ciphertext = $profile[$prefix . '_ciphertext'] ?? null;
        $nonce = $profile[$prefix . '_nonce'] ?? null;
        $tag = $profile[$prefix . '_tag'] ?? null;
        if (!is_string($ciphertext) || $ciphertext === '' || !is_string($nonce) || !is_string($tag)) {
            throw new RuntimeException('Encrypted fiscal secret unavailable.');
        }

        return $this->vault->open([
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
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
            'tpAmb' => 2,
            'razaosocial' => (string) $profile['razao_social'],
            'cnpj' => (string) $profile['titular_cnpj'],
            'siglaUF' => (string) $profile['uf'],
            'schemes' => '',
            'versao' => (string) $profile['schema_versao'],
            'tokenIBPT' => null,
            'CSC' => $csc === '' ? null : $csc,
            'CSCid' => $profile['csc_id'] === null ? null : (string) $profile['csc_id'],
            'aProxyConf' => null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array{code:string,message:string,application_version:string,received_at:string} */
    private function parseStatus(string $xml): array
    {
        $dom = new DOMDocument();
        if ($xml === '' || !@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('A SEFAZ retornou uma resposta inválida.');
        }
        $environment = $this->nodeValue($dom, 'tpAmb');
        $code = $this->nodeValue($dom, 'cStat');
        $message = $this->safeText($this->nodeValue($dom, 'xMotivo'));
        if ($environment !== '2' || preg_match('/^\d{3}$/', $code) !== 1 || $message === '') {
            throw new InvalidArgumentException('A SEFAZ retornou uma resposta incompleta para homologação.');
        }

        return [
            'code' => $code,
            'message' => $message,
            'application_version' => $this->safeText($this->nodeValue($dom, 'verAplic')),
            'received_at' => $this->safeText($this->nodeValue($dom, 'dhRecbto')),
        ];
    }

    private function nodeValue(DOMDocument $dom, string $name): string
    {
        $node = $dom->getElementsByTagName($name)->item(0);

        return $node === null ? '' : trim((string) $node->nodeValue);
    }

    private function safeText(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($value)) ?? '';

        return substr(trim($value), 0, 180);
    }
}
