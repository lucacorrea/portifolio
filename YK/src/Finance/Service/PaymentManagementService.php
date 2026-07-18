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

    /**
     * @param array<int,mixed> $accountIds
     * @return array{client_id:int,client_name:string,count:int,total:string,account_ids:array<int,int>}
     */
    public function registerAccountsReceivableBatchPayment(array $accountIds, string $form, ?string $notes, int $userId): array
    {
        return $this->accounts->registerBatchPayment($accountIds, $form, $notes, $userId);
    }
}
