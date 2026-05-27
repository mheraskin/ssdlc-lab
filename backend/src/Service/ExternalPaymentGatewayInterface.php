<?php

namespace App\Service;

use App\Entity\Transaction;

/**
 * Boundary to external payment systems / partner APIs.
 *
 * The interface is what the application depends on; production would bind a real
 * adapter (card network, SEPA, partner bank). The lab binds a mock implementation.
 */
interface ExternalPaymentGatewayInterface
{
    public function submit(Transaction $transaction): ExternalPaymentResult;
}
