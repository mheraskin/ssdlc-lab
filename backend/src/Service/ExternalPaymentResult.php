<?php

namespace App\Service;

final class ExternalPaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $reference,
        public readonly ?string $failureReason = null,
    ) {
    }
}
