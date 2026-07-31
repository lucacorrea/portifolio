<?php
declare(strict_types=1);
namespace App\Integration\SO;
use RuntimeException;
use Throwable;
final class SoIntegrationException extends RuntimeException
{
    public function __construct(
        string $message = 'Integração do SO indisponível.',
        private readonly string $reason = 'unavailable',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
