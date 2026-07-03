<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

final class AuditService
{
    /** @var list<string> */
    private const SECRET_KEYS = ['senha', 'password', 'senha_hash', 'token', 'secret', 'authorization', 'cookie', 'installation_key', 'db_password'];

    public function __construct(private readonly AuditLogRepository $auditLogs)
    {
    }

    public function record(?int $userId, ?int $targetUserId, string $action, string $module, ?string $description = null, ?array $before = null, ?array $after = null): int
    {
        return $this->auditLogs->record(
            $userId,
            $targetUserId,
            mb_substr($action, 0, 120),
            mb_substr($module, 0, 80),
            $description === null ? null : mb_substr($description, 0, 255),
            $before === null ? null : $this->sanitize($before),
            $after === null ? null : $this->sanitize($after),
        );
    }

    /** @param array<string,mixed> $data */
    private function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);

            foreach (self::SECRET_KEYS as $secret) {
                if (str_contains($normalized, $secret)) {
                    $clean[$key] = '[redacted]';
                    continue 2;
                }
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $this->safeValue($value);
        }

        return $clean;
    }

    private function safeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return mb_substr((string) $value, 0, 500);
    }
}
