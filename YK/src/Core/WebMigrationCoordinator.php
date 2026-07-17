<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Throwable;

final class WebMigrationCoordinator
{
    private const STATE_VERSION = 1;
    private const INITIAL_FAILURE_COOLDOWN = 300;
    private const MAX_FAILURE_COOLDOWN = 21600;
    private const BUSY_COOLDOWN = 30;
    private const SUCCESS_RECHECK_INTERVAL = 3600;

    private readonly Closure $runner;
    private readonly Closure $clock;
    private bool $runnerAttempted = false;

    public function __construct(
        private readonly string $migrationsDirectory,
        private readonly string $statePath,
        private readonly string $lockPath,
        callable $runner,
        ?callable $clock = null
    ) {
        $this->runner = Closure::fromCallable($runner);
        $this->clock = $clock === null
            ? static fn (): int => time()
            : Closure::fromCallable($clock);
    }

    public function run(): void
    {
        $this->runnerAttempted = false;
        try {
            $this->runSafely();
        } catch (Throwable $exception) {
            error_log('Web migration coordination failed: ' . $exception::class);
            if (!$this->runnerAttempted) {
                $this->runWithoutFileState();
            }
        }
    }

    private function runSafely(): void
    {
        if (!$this->ensureRuntimeDirectory()) {
            $this->runWithoutFileState();
            return;
        }

        $lock = @fopen($this->lockPath, 'c+');
        if ($lock === false) {
            $this->runWithoutFileState();
            return;
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return;
            }

            $fingerprint = $this->fingerprint();
            $now = ($this->clock)();
            $state = $this->readState();
            if ($this->shouldSkip($state, $fingerprint, $now)) {
                return;
            }

            $previousFailures = $this->failureCount($state, $fingerprint);
            $this->writeState([
                'version' => self::STATE_VERSION,
                'status' => 'busy',
                'fingerprint' => $fingerprint,
                'last_attempt_at' => $now,
                'last_success_at' => $this->lastSuccessAt($state),
                'failure_count' => $previousFailures,
                'next_retry_at' => $now + self::BUSY_COOLDOWN,
            ]);

            try {
                $this->runnerAttempted = true;
                $completed = (bool) ($this->runner)();
                if (!$completed) {
                    $this->writeState([
                        'version' => self::STATE_VERSION,
                        'status' => 'busy',
                        'fingerprint' => $fingerprint,
                        'last_attempt_at' => $now,
                        'last_success_at' => $this->lastSuccessAt($state),
                        'failure_count' => $previousFailures,
                        'next_retry_at' => $now + self::BUSY_COOLDOWN,
                    ]);
                    return;
                }

                $this->writeState([
                    'version' => self::STATE_VERSION,
                    'status' => 'success',
                    'fingerprint' => $fingerprint,
                    'last_attempt_at' => $now,
                    'last_success_at' => $now,
                    'failure_count' => 0,
                    'next_retry_at' => $now + self::SUCCESS_RECHECK_INTERVAL,
                ]);
            } catch (Throwable) {
                $failureCount = $previousFailures + 1;
                $this->writeState([
                    'version' => self::STATE_VERSION,
                    'status' => 'failed',
                    'fingerprint' => $fingerprint,
                    'last_attempt_at' => $now,
                    'last_success_at' => $this->lastSuccessAt($state),
                    'failure_count' => $failureCount,
                    'next_retry_at' => $now + $this->failureCooldown($failureCount),
                ]);
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runWithoutFileState(): void
    {
        $this->runnerAttempted = true;
        try {
            ($this->runner)();
        } catch (Throwable $exception) {
            error_log('Automatic web migration failed: ' . $exception::class);
        }
    }

    private function ensureRuntimeDirectory(): bool
    {
        $directories = [dirname($this->statePath), dirname($this->lockPath)];
        foreach (array_unique($directories) as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
                return false;
            }
        }
        return true;
    }

    private function fingerprint(): string
    {
        $directory = realpath($this->migrationsDirectory);
        if ($directory === false || !is_dir($directory)) {
            throw new MigrationException('Diretório de migrations não encontrado.');
        }

        $paths = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        $context = hash_init('sha256');
        foreach ($paths as $path) {
            $content = file_get_contents($path);
            if (!is_string($content)) {
                throw new MigrationException('Migration ilegível.');
            }
            hash_update($context, basename($path) . "\0");
            hash_update($context, str_replace(["\r\n", "\r"], "\n", $content) . "\0");
        }
        return hash_final($context);
    }

    /** @param array<string, mixed> $state */
    private function shouldSkip(array $state, string $fingerprint, int $now): bool
    {
        if (($state['fingerprint'] ?? null) !== $fingerprint) {
            return false;
        }
        if (($state['status'] ?? null) === 'success') {
            return (int) ($state['next_retry_at'] ?? 0) > $now;
        }
        return in_array($state['status'] ?? null, ['failed', 'busy'], true)
            && (int) ($state['next_retry_at'] ?? 0) > $now;
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        if (!is_file($this->statePath)) {
            return [];
        }
        $json = file_get_contents($this->statePath);
        if (!is_string($json)) {
            return [];
        }
        $state = json_decode($json, true);
        return is_array($state) && ($state['version'] ?? null) === self::STATE_VERSION ? $state : [];
    }

    /** @param array<string, int|string> $state */
    private function writeState(array $state): void
    {
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $temporaryPath = $this->statePath . '.tmp.' . bin2hex(random_bytes(8));
        if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
            throw new MigrationException('Não foi possível persistir o estado das migrations.');
        }
        if (!@rename($temporaryPath, $this->statePath)) {
            @unlink($temporaryPath);
            throw new MigrationException('Não foi possível publicar o estado das migrations.');
        }
    }

    /** @param array<string, mixed> $state */
    private function failureCount(array $state, string $fingerprint): int
    {
        return ($state['fingerprint'] ?? null) === $fingerprint
            ? max(0, (int) ($state['failure_count'] ?? 0))
            : 0;
    }

    /** @param array<string, mixed> $state */
    private function lastSuccessAt(array $state): int
    {
        return max(0, (int) ($state['last_success_at'] ?? 0));
    }

    private function failureCooldown(int $failureCount): int
    {
        $exponent = min(7, max(0, $failureCount - 1));
        return min(self::MAX_FAILURE_COOLDOWN, self::INITIAL_FAILURE_COOLDOWN * (2 ** $exponent));
    }
}
