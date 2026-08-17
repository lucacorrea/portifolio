<?php

declare(strict_types=1);

namespace App\Nfse\Contract;

interface NfseTransportInterface
{
    public function send(string $operation, string $soapXml): string;
}
