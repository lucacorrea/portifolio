<?php

declare(strict_types=1);

namespace App\Nfse\Service;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;

final class DpsXmlSigner
{
    public function sign(string $xml, Certificate $certificate): string
    {
        return Signer::sign(
            $certificate,
            $xml,
            'infDPS',
            'id',
            OPENSSL_ALGO_SHA256,
            Signer::CANONICAL,
            'DPS'
        );
    }
}
