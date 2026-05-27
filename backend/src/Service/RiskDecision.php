<?php

namespace App\Service;

use App\Entity\Transaction;

final class RiskDecision
{
    private function __construct(
        public readonly string $status,
        public readonly bool $requiresMfa,
        public readonly ?string $reason = null,
    ) {
    }

    public static function ok(): self
    {
        return new self(Transaction::RISK_OK, false);
    }

    public static function review(string $reason): self
    {
        return new self(Transaction::RISK_REVIEW, true, $reason);
    }

    public static function blocked(string $reason): self
    {
        return new self(Transaction::RISK_BLOCKED, false, $reason);
    }

    public function isBlocked(): bool
    {
        return Transaction::RISK_BLOCKED === $this->status;
    }
}
