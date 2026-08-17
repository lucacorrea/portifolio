<?php

declare(strict_types=1);

namespace App\Nfse\Contract;

use App\Nfse\DTO\NfseEventResult;
use App\Nfse\DTO\NfseQueryResult;
use App\Nfse\DTO\NfseSubmissionResult;

interface NfseProviderInterface
{
    public function submit(string $signedDps): NfseSubmissionResult;

    /** @param array<string,string> $context */
    public function query(array $context): NfseQueryResult;

    public function cancel(string $signedEvent): NfseEventResult;

    public function substitute(string $signedEvent): NfseEventResult;
}
