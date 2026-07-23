<?php

declare(strict_types=1);

namespace App\Fiscal\Storage;

use InvalidArgumentException;
use NFePHP\Common\Certificate\PublicKey;
use RuntimeException;
use Throwable;

final class FiscalCertificateStorage
{
    private const MAX_BYTES = 2_097_152;
    private const REFERENCE_PATTERN = '#^fiscal/certificados/([a-f0-9]{32}\.p12)$#';

    public function __construct(private readonly string $storageRoot)
    {
        if (trim($this->storageRoot) === '' || str_contains($this->storageRoot, "\0")) {
            throw new InvalidArgumentException('Diretório fiscal inválido.');
        }
    }

    public static function forProjectRoot(string $projectRoot): self
    {
        return new self(self::resolveStorageRoot($projectRoot));
    }

    public static function resolveStorageRoot(string $projectRoot): string
    {
        $projectRoot = rtrim(trim($projectRoot), '/\\');
        if ($projectRoot === '' || str_contains($projectRoot, "\0")) {
            throw new InvalidArgumentException('Diretório do projeto inválido.');
        }

        return dirname($projectRoot, 2)
            . DIRECTORY_SEPARATOR . 'configuracoes'
            . DIRECTORY_SEPARATOR . 'yk'
            . DIRECTORY_SEPARATOR . 'fiscal'
            . DIRECTORY_SEPARATOR . 'certificados';
    }

    /** @param array<string,mixed> $upload */
    public function store(array $upload, string $password, string $expectedCnpj): string
    {
        return $this->storeWithMetadata($upload, $password, $expectedCnpj)['reference'];
    }

    /**
     * @param array<string,mixed> $upload
     * @return array{reference:string,file_sha256:string,fingerprint:string,serial:string,holder_cnpj:string,holder_name:string,valid_from:int,valid_to:int}
     */
    public function storeWithMetadata(array $upload, string $password, string $expectedCnpj): array
    {
        $temporaryPath = $this->uploadedPath($upload);
        $contents = file_get_contents($temporaryPath);
        if (!is_string($contents) || $contents === '') {
            throw new InvalidArgumentException('Certificado fiscal inválido.');
        }

        $metadata = $this->inspectPkcs12($contents, $password, $expectedCnpj);
        $this->ensureStorageDirectory();

        $filename = bin2hex(random_bytes(16)) . '.p12';
        $temporaryDestination = $this->storageRoot . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(12));
        $destination = $this->storageRoot . DIRECTORY_SEPARATOR . $filename;

        try {
            $this->writeExclusive($temporaryDestination, $contents);
            if (!rename($temporaryDestination, $destination)) {
                throw new RuntimeException('Não foi possível concluir o armazenamento do certificado fiscal.');
            }
            if (!chmod($destination, 0600)) {
                @unlink($destination);
                throw new RuntimeException('Não foi possível proteger o certificado fiscal armazenado.');
            }
        } catch (Throwable $exception) {
            if (is_file($temporaryDestination)) {
                @unlink($temporaryDestination);
            }
            throw $exception;
        }

        return ['reference' => 'fiscal/certificados/' . $filename] + $metadata;
    }

    /**
     * Persiste a referência nova antes de remover a anterior. Se a persistência
     * falhar, o novo arquivo é apagado e o certificado anterior permanece intacto.
     *
     * @param array<string,mixed> $upload
     * @param callable(string):void $persistReference
     */
    public function replace(
        array $upload,
        string $password,
        string $expectedCnpj,
        ?string $currentReference,
        callable $persistReference
    ): string {
        $newReference = $this->store($upload, $password, $expectedCnpj);
        try {
            $persistReference($newReference);
        } catch (Throwable $exception) {
            $this->delete($newReference);
            throw $exception;
        }

        if ($currentReference !== null && $currentReference !== '' && $currentReference !== $newReference) {
            $this->delete($currentReference);
        }

        return $newReference;
    }

    /**
     * @param array<string,mixed> $upload
     * @param callable(array{reference:string,file_sha256:string,fingerprint:string,serial:string,holder_cnpj:string,holder_name:string,valid_from:int,valid_to:int}):void $persistMetadata
     * @return array{reference:string,file_sha256:string,fingerprint:string,serial:string,holder_cnpj:string,holder_name:string,valid_from:int,valid_to:int}
     */
    public function replaceWithMetadata(
        array $upload,
        string $password,
        string $expectedCnpj,
        ?string $currentReference,
        callable $persistMetadata
    ): array {
        $metadata = $this->storeWithMetadata($upload, $password, $expectedCnpj);
        try {
            $persistMetadata($metadata);
        } catch (Throwable $exception) {
            $this->delete($metadata['reference']);
            throw $exception;
        }

        if ($currentReference !== null && $currentReference !== '' && $currentReference !== $metadata['reference']) {
            $this->delete($currentReference);
        }

        return $metadata;
    }

    public function resolve(string $reference): ?string
    {
        if (preg_match(self::REFERENCE_PATTERN, $reference, $matches) !== 1) {
            return null;
        }

        $root = realpath($this->storageRoot);
        $candidate = $this->storageRoot . DIRECTORY_SEPARATOR . $matches[1];
        $resolved = is_file($candidate) ? realpath($candidate) : false;
        if ($root === false || $resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $resolved;
    }

    public function delete(?string $reference): bool
    {
        if ($reference === null || $reference === '') {
            return true;
        }

        $path = $this->resolve($reference);
        return $path === null || !is_file($path) || @unlink($path);
    }

    /** @return array{file_sha256:string,fingerprint:string,serial:string,holder_cnpj:string,holder_name:string,valid_from:int,valid_to:int} */
    public function inspectPkcs12(string $contents, string $password, string $expectedCnpj): array
    {
        if (strlen($contents) <= 0 || strlen($contents) > self::MAX_BYTES) {
            throw new InvalidArgumentException('O certificado deve ter no máximo 2 MB.');
        }
        $expectedCnpj = self::normalizeCnpj($expectedCnpj);
        if (!function_exists('openssl_pkcs12_read')
            || !function_exists('openssl_x509_parse')
            || !function_exists('openssl_x509_check_private_key')
        ) {
            throw new RuntimeException('OpenSSL com suporte a PKCS#12 não está disponível.');
        }

        $certificates = [];
        if (!@openssl_pkcs12_read($contents, $certificates, $password)
            || !isset($certificates['cert'], $certificates['pkey'])
            || !is_string($certificates['cert'])
            || !is_string($certificates['pkey'])
            || !@openssl_x509_check_private_key($certificates['cert'], $certificates['pkey'])
        ) {
            throw new InvalidArgumentException('Certificado ou senha fiscal inválidos.');
        }

        $parsed = @openssl_x509_parse($certificates['cert'], false);
        if (!is_array($parsed)) {
            throw new InvalidArgumentException('Não foi possível validar o certificado fiscal.');
        }
        $validFrom = (int) ($parsed['validFrom_time_t'] ?? 0);
        $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
        $now = time();
        if ($validFrom <= 0 || $validTo <= 0 || $validFrom > $now || $validTo < $now) {
            throw new InvalidArgumentException('O certificado fiscal está fora do período de validade.');
        }

        $subject = is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [];
        $subjectCnpj = $this->certificateCnpj($certificates['cert'], $subject);
        if ($subjectCnpj === null) {
            throw new InvalidArgumentException('Não foi possível identificar o CNPJ do titular no certificado A1.');
        }
        if (!hash_equals($expectedCnpj, $subjectCnpj)) {
            throw new InvalidArgumentException('O CNPJ do certificado A1 não corresponde ao CNPJ cadastrado da empresa.');
        }

        $fingerprint = $this->certificateFingerprint($certificates['cert']);
        $serial = trim((string) ($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? ''));
        $holderName = $this->subjectName(is_array($parsed['subject'] ?? null) ? $parsed['subject'] : []);
        if ($fingerprint === '' || $serial === '' || $holderName === '') {
            throw new InvalidArgumentException('Os metadados do certificado fiscal estão incompletos.');
        }

        return [
            'file_sha256' => hash('sha256', $contents),
            'fingerprint' => $fingerprint,
            'serial' => substr($serial, 0, 120),
            'holder_cnpj' => $subjectCnpj,
            'holder_name' => substr($holderName, 0, 180),
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ];
    }

    public static function normalizeCnpj(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            throw new InvalidArgumentException('CNPJ esperado inválido.');
        }

        foreach ([12 => [5,4,3,2,9,8,7,6,5,4,3,2], 13 => [6,5,4,3,2,9,8,7,6,5,4,3,2]] as $position => $weights) {
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += ((int) $digits[$index]) * $weight;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $digits[$position] !== $digit) {
                throw new InvalidArgumentException('CNPJ esperado inválido.');
            }
        }

        return $digits;
    }

    /** @param array<string,mixed> $upload */
    private function uploadedPath(array $upload): string
    {
        $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O certificado ultrapassa o limite de 2 MB.',
                UPLOAD_ERR_PARTIAL => 'O envio do certificado foi interrompido. Tente novamente.',
                UPLOAD_ERR_NO_FILE => 'Selecione um certificado PFX ou P12.',
                default => 'Não foi possível receber o certificado fiscal.',
            });
        }

        $path = (string) ($upload['tmp_name'] ?? '');
        $name = basename((string) ($upload['name'] ?? ''));
        $reportedSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        $actualSize = $path !== '' && is_file($path) ? filesize($path) : false;
        if ($path === '' || !is_uploaded_file($path)) {
            throw new InvalidArgumentException('O arquivo enviado não é um upload válido.');
        }
        if (preg_match('/\.(?:pfx|p12)$/i', $name) !== 1) {
            throw new InvalidArgumentException('Envie um certificado PFX ou P12.');
        }
        if (!is_int($reportedSize) || !is_int($actualSize)
            || $reportedSize <= 0 || $actualSize <= 0
            || $reportedSize > self::MAX_BYTES || $actualSize > self::MAX_BYTES
        ) {
            throw new InvalidArgumentException('O certificado deve ter no máximo 2 MB.');
        }

        return $path;
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageRoot)
            && !mkdir($this->storageRoot, 0700, true)
            && !is_dir($this->storageRoot)
        ) {
            throw new RuntimeException('Não foi possível preparar o armazenamento fiscal.');
        }
        if (!chmod($this->storageRoot, 0700)) {
            throw new RuntimeException('Não foi possível proteger o armazenamento fiscal.');
        }
    }

    private function writeExclusive(string $path, string $contents): void
    {
        $handle = @fopen($path, 'xb');
        if (!is_resource($handle)) {
            throw new RuntimeException('Não foi possível criar o arquivo fiscal temporário.');
        }

        try {
            if (!chmod($path, 0600)) {
                throw new RuntimeException('Não foi possível proteger o arquivo fiscal temporário.');
            }
            $written = 0;
            $length = strlen($contents);
            while ($written < $length) {
                $chunk = fwrite($handle, substr($contents, $written));
                if (!is_int($chunk) || $chunk <= 0) {
                    throw new RuntimeException('Não foi possível gravar o certificado fiscal.');
                }
                $written += $chunk;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Não foi possível concluir a gravação do certificado fiscal.');
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string,mixed> $subject */
    private function certificateCnpj(string $certificate, array $subject): ?string
    {
        if (class_exists(PublicKey::class)) {
            try {
                $oidCnpj = (new PublicKey($certificate))->cnpj();
                if (is_scalar($oidCnpj) && trim((string) $oidCnpj) !== '') {
                    return self::normalizeCnpj((string) $oidCnpj);
                }
            } catch (Throwable) {
                // Mantém compatibilidade com certificados antigos sem o OID ICP-Brasil.
            }
        }

        return $this->subjectCnpj($subject);
    }

    /** @param array<string,mixed> $subject */
    private function subjectCnpj(array $subject): ?string
    {
        foreach ($subject as $value) {
            foreach ((array) $value as $entry) {
                if (!is_scalar($entry)) {
                    continue;
                }
                if (preg_match('/(?<!\d)(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})(?!\d)/', (string) $entry, $matches) === 1) {
                    try {
                        return self::normalizeCnpj($matches[1]);
                    } catch (InvalidArgumentException) {
                        continue;
                    }
                }
            }
        }

        return null;
    }

    private function certificateFingerprint(string $certificate): string
    {
        if (function_exists('openssl_x509_fingerprint')) {
            $fingerprint = @openssl_x509_fingerprint($certificate, 'sha256', false);
            if (is_string($fingerprint) && preg_match('/^[a-f0-9]{64}$/i', $fingerprint) === 1) {
                return strtolower($fingerprint);
            }
        }

        if (preg_match('#-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----#s', $certificate, $matches) !== 1) {
            return '';
        }
        $der = base64_decode(preg_replace('/\s+/', '', $matches[1]) ?? '', true);

        return is_string($der) && $der !== '' ? hash('sha256', $der) : '';
    }

    /** @param array<string,mixed> $subject */
    private function subjectName(array $subject): string
    {
        foreach (['CN', 'commonName', 'O', 'organizationName'] as $key) {
            $value = $subject[$key] ?? null;
            if (is_array($value)) {
                $value = reset($value);
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }
}
