<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use Throwable;

final class FiscalSafeLogger
{
    public static function record(Throwable $exception, string $operation): string
    {
        $correlationId = bin2hex(random_bytes(8));
        error_log((string) json_encode([
            'channel' => 'fiscal',
            'correlation_id' => $correlationId,
            'operation' => preg_replace('/[^a-z0-9_.-]/i', '', $operation),
            'exception_class' => get_class($exception),
            'occurred_at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES));
        return $correlationId;
    }
}
