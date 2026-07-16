<?php

declare(strict_types=1);

namespace App\CRM\Service;

use App\CRM\DTO\ClientFormData;
use App\CRM\Import\ClientPdfParser;
use App\CRM\Repository\ClientRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ClientImportService
{
    private const STAGING_TTL = 1800;
    private const PREVIEW_LIMIT = 50;

    public function __construct(
        private readonly ClientRepository $clients,
        private readonly ClientPdfParser $parser
    ) {
    }

    /** @return array<string, mixed> */
    public function analyze(string $filePath, string $originalName, string $ownerKey): array
    {
        $this->assertOwnerKey($ownerKey);
        $parsed = $this->parser->parse($filePath);
        $existingCodes = $this->clients->importedSourceCodes();
        $existingIdentities = $this->existingIdentityKeys();
        $identitiesInPdf = [];
        $preview = [];
        $readyRows = [];
        $summary = [
            'total' => count($parsed['rows']),
            'ready' => 0,
            'existing' => 0,
            'possible_duplicates' => 0,
            'warnings' => 0,
            'invalid' => 0,
        ];

        foreach ($parsed['rows'] as $row) {
            $status = 'ready';
            $message = 'Pronto para importar';
            $code = (string) ($row['code'] ?? '');
            $identity = $this->identityKey((string) ($row['name'] ?? ''), $row['phone'] ?? null);

            try {
                ClientFormData::fromArray($row);
            } catch (InvalidArgumentException $exception) {
                $status = 'invalid';
                $message = $exception->getMessage();
                ++$summary['invalid'];
            }

            if ($status === 'ready' && isset($existingCodes[$code])) {
                $status = 'existing';
                $message = 'Código A7 já importado';
                ++$summary['existing'];
            } elseif ($status === 'ready' && $identity !== null && isset($existingIdentities[$identity])) {
                $status = 'possible_duplicate';
                $message = 'Será importado; possível duplicidade com cadastro atual';
                ++$summary['possible_duplicates'];
                ++$summary['ready'];
                $readyRows[] = $row;
            } elseif ($status === 'ready') {
                if ($identity !== null && isset($identitiesInPdf[$identity])) {
                    $message = 'Pronto; possível duplicidade no próprio PDF';
                    ++$summary['warnings'];
                }
                ++$summary['ready'];
                $readyRows[] = $row;
            }

            if ($identity !== null) {
                $identitiesInPdf[$identity] = true;
            }
            if (count($preview) < self::PREVIEW_LIMIT) {
                $preview[] = [
                    'code' => $code,
                    'name' => (string) ($row['name'] ?? ''),
                    'phone' => $row['phone'] ?? null,
                    'city' => $row['city'] ?? null,
                    'status' => $status,
                    'message' => $message,
                ];
            }
        }

        $token = bin2hex(random_bytes(24));
        $payload = [
            'version' => 1,
            'created_at' => time(),
            'expires_at' => time() + self::STAGING_TTL,
            'source_name' => mb_substr(basename($originalName), 0, 180),
            'pages' => $parsed['pages'],
            'summary' => $summary,
            'preview' => $preview,
            'rows' => $readyRows,
        ];
        $this->writeStaging($ownerKey, $token, $payload);

        return $payload + ['token' => $token];
    }

    /** @return array{imported:int,skipped:int} */
    public function confirm(string $token, string $ownerKey): array
    {
        $this->assertOwnerKey($ownerKey);
        $payload = $this->readStaging($ownerKey, $token);
        $existingCodes = $this->clients->importedSourceCodes();
        $batch = [];
        $skipped = 0;

        foreach ($payload['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Os dados temporários da importação são inválidos.');
            }
            $code = (string) ($row['code'] ?? '');
            if (isset($existingCodes[$code])) {
                ++$skipped;
                continue;
            }
            $batch[] = ['code' => $code, 'data' => ClientFormData::fromArray($row)];
        }

        $imported = $this->clients->createImportedBatch($batch);
        $this->deleteStaging($ownerKey, $token);
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function discard(string $token, string $ownerKey): void
    {
        $this->assertOwnerKey($ownerKey);
        $this->deleteStaging($ownerKey, $token);
    }

    /** @return array<string, true> */
    private function existingIdentityKeys(): array
    {
        $keys = [];
        foreach ($this->clients->clientIdentityData() as $client) {
            $key = $this->identityKey((string) $client['nome'], $client['telefone']);
            if ($key !== null) {
                $keys[$key] = true;
            }
        }
        return $keys;
    }

    private function identityKey(string $name, mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $name = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name) ?? ''), 'UTF-8');
        return $name === '' || $digits === '' ? null : hash('sha256', $name . '|' . $digits);
    }

    /** @param array<string, mixed> $payload */
    private function writeStaging(string $ownerKey, string $token, array $payload): void
    {
        $directory = $this->stagingDirectory($ownerKey);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar a importação.');
        }
        $this->cleanupStaging($directory);

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível preparar os dados da importação.', 0, $exception);
        }
        $path = $directory . DIRECTORY_SEPARATOR . $token . '.json';
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível salvar a análise temporária.');
        }
        @chmod($path, 0600);
    }

    /** @return array<string, mixed> */
    private function readStaging(string $ownerKey, string $token): array
    {
        $path = $this->stagingPath($ownerKey, $token);
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new InvalidArgumentException('A análise expirou. Envie o PDF novamente.');
        }
        try {
            $payload = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('A análise temporária é inválida. Envie o PDF novamente.', 0, $exception);
        }
        if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
            $this->deleteStaging($ownerKey, $token);
            throw new InvalidArgumentException('A análise expirou. Envie o PDF novamente.');
        }
        return $payload;
    }

    private function deleteStaging(string $ownerKey, string $token): void
    {
        $path = $this->stagingPath($ownerKey, $token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function cleanupStaging(string $directory): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            if (is_file($path) && filemtime($path) !== false && filemtime($path) < time() - self::STAGING_TTL) {
                @unlink($path);
            }
        }
    }

    private function stagingPath(string $ownerKey, string $token): string
    {
        if (preg_match('/^[a-f0-9]{48}$/', $token) !== 1) {
            throw new InvalidArgumentException('Token de importação inválido.');
        }
        return $this->stagingDirectory($ownerKey) . DIRECTORY_SEPARATOR . $token . '.json';
    }

    private function stagingDirectory(string $ownerKey): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'yk-client-imports'
            . DIRECTORY_SEPARATOR . hash('sha256', $ownerKey);
    }

    private function assertOwnerKey(string $ownerKey): void
    {
        if ($ownerKey === '' || strlen($ownerKey) > 256 || str_contains($ownerKey, "\0")) {
            throw new InvalidArgumentException('Sessão de importação inválida.');
        }
    }
}
