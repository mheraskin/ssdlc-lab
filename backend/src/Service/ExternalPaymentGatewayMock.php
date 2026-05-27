<?php

namespace App\Service;

use App\Entity\Transaction;

/**
 * Mock external payment gateway used for the lab. It always "succeeds" and returns a
 * synthetic reference. No real money movement and no third-party network call happen.
 */
class ExternalPaymentGatewayMock implements ExternalPaymentGatewayInterface
{
    public function submit(Transaction $transaction): ExternalPaymentResult
    {
        return new ExternalPaymentResult(
            success: true,
            reference: 'MOCK-'.strtoupper(bin2hex(random_bytes(6))),
        );
    }
}
