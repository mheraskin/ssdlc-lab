<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TransactionRepository;

/**
 * Fraud / Risk Rules service.
 *
 * Implements the simple rules from the architecture:
 *   - too many payments in a short window  => blocked (velocity / fraud)
 *   - payment at or above a high-value cap  => requires additional confirmation (MFA)
 *
 * (Account ownership is enforced separately by the AccountVoter, and balance/limits
 *  by PaymentService — those are integrity checks rather than risk scoring.)
 */
class RiskService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly int $mfaThresholdCents,
        private readonly int $maxPaymentsPerMinute,
    ) {
    }

    public function evaluate(User $user, int $amountCents): RiskDecision
    {
        $oneMinuteAgo = new \DateTimeImmutable('-1 minute');
        $recentCount = $this->transactions->countByUserSince($user, $oneMinuteAgo);

        if ($recentCount >= $this->maxPaymentsPerMinute) {
            return RiskDecision::blocked(sprintf(
                'Too many payments in a short period (%d in the last minute).',
                $recentCount,
            ));
        }

        if ($amountCents >= $this->mfaThresholdCents) {
            return RiskDecision::review(sprintf(
                'High-value payment (>= %d.%02d) requires additional confirmation.',
                intdiv($this->mfaThresholdCents, 100),
                $this->mfaThresholdCents % 100,
            ));
        }

        return RiskDecision::ok();
    }
}
