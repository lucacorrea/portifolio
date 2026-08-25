<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Tax\Decimal;
use InvalidArgumentException;

final class FiscalPaymentAllocator
{
    /**
     * Aloca a interseção de um segmento fiscal com os pagamentos, em ordem cronológica.
     *
     * @param array<int,array<string,mixed>> $payments
     * @return array<int,array<string,mixed>>
     */
    public function allocate(array $payments, int $offsetCents, int $amountCents): array
    {
        if ($offsetCents < 0 || $amountCents <= 0) {
            throw new InvalidArgumentException('Segmento de pagamento fiscal inválido.');
        }

        $segmentStart = $offsetCents;
        $segmentEnd = $offsetCents + $amountCents;
        $paymentStart = 0;
        $allocated = [];
        foreach ($payments as $payment) {
            if (!is_array($payment) || (int)($payment['id'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Pagamento fiscal inválido.');
            }
            $paymentCents = Decimal::moneyToCents((string)($payment['valor'] ?? ''));
            $paymentEnd = $paymentStart + $paymentCents;
            $intersection = max(0, min($segmentEnd, $paymentEnd) - max($segmentStart, $paymentStart));
            if ($intersection > 0) {
                $row = $payment;
                $row['valor'] = Decimal::formatCents($intersection);
                $allocated[] = $row;
            }
            $paymentStart = $paymentEnd;
            if ($paymentStart >= $segmentEnd) break;
        }
        return $allocated;
    }
}
