<?php

namespace App\Service;

use App\Entity\Transaction;

final class PaymentResult
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_MFA_REQUIRED = 'mfa_required';
    public const STATUS_REJECTED = 'rejected';

    private function __construct(
        public readonly string $status,
        public readonly ?Transaction $transaction,
        public readonly string $message,
    ) {
    }

    public static function completed(Transaction $transaction, string $message): self
    {
        return new self(self::STATUS_COMPLETED, $transaction, $message);
    }

    public static function mfaRequired(Transaction $transaction, string $message): self
    {
        return new self(self::STATUS_MFA_REQUIRED, $transaction, $message);
    }

    public static function rejected(?Transaction $transaction, string $message): self
    {
        return new self(self::STATUS_REJECTED, $transaction, $message);
    }
}
