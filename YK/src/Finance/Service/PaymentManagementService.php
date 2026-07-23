<?php

declare(strict_types=1);

namespace App\Finance\Service;

use PDO;
use Throwable;

final class PaymentManagementService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly AccountsReceivableManagementService $accounts,
        private readonly ReceiptService $receipts
    )
    {
    }

    public function registerAccountsReceivablePayment(int $accountId, string $value, string $form, ?string $notes, int $userId): int
    {
        return $this->accounts->registerPayment($accountId, $value, $form, $notes, $userId);
    }

    /** @return array{payment_id:int,receipt_id:int,receipt_created:bool,account_status:string,idempotent:bool} */
    public function registerFinalizedOrderPayment(
        int $orderId,
        string $value,
        string $form,
        int|string $installmentCount,
        ?string $notes,
        string $paymentToken,
        int $userId
    ): array {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();
        try {
            $payment = $this->accounts->registerOrderPayment(
                $orderId,
                $value,
                $form,
                $installmentCount,
                $notes,
                $paymentToken,
                $userId
            );
            $receipt = $this->receipts->emitForPayment($payment['payment_id'], $userId);
            if ($ownsTransaction) $this->connection->commit();

            return [
                'payment_id' => $payment['payment_id'],
                'receipt_id' => $receipt['id'],
                'receipt_created' => $receipt['created'],
                'account_status' => $payment['account_status'],
                'idempotent' => $payment['idempotent'],
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
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
