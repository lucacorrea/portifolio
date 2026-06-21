<?php

declare(strict_types=1);

namespace App\Contracts;

interface BarcodeProductProviderInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    public function lookup(string $barcode): array;
}
