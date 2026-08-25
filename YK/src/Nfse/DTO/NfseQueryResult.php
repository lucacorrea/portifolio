<?php

declare(strict_types=1);

namespace App\Nfse\DTO;

final class NfseQueryResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $protocol,
        public readonly ?string $providerDpsId,
        public readonly ?string $accessKey,
        public readonly ?string $invoiceNumber,
        public readonly ?string $pdfUrl,
        public readonly ?string $code,
        public readonly string $message,
        public readonly string $rawResponse
    ) {
    }
}
