<?php

declare(strict_types=1);

namespace App\Finance\Service;

final class PaymentManagementService
{
    public function __construct(private readonly AccountsReceivableManagementService $accounts)
    {
    }

    public function registerAccountsReceivablePayment(int $accountId, string $value, string $form, ?string $notes, int $userId): void
    {
        $this->accounts->registerPayment($accountId, $value, $form, $notes, $userId);
    }
}
