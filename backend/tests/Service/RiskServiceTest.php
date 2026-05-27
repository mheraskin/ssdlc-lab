<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\RiskService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RiskService::class)]
class RiskServiceTest extends TestCase
{
    private function service(int $recentCount): RiskService
    {
        $repo = $this->createStub(TransactionRepository::class);
        $repo->method('countByUserSince')->willReturn($recentCount);

        // threshold 10,000.00 (1,000,000 cents), max 3 payments/minute
        return new RiskService($repo, 1_000_000, 3);
    }

    public function testLowValuePaymentIsOk(): void
    {
        $decision = $this->service(0)->evaluate(new User(), 5_000);

        self::assertSame(Transaction::RISK_OK, $decision->status);
        self::assertFalse($decision->requiresMfa);
        self::assertFalse($decision->isBlocked());
    }

    public function testHighValuePaymentRequiresMfa(): void
    {
        $decision = $this->service(0)->evaluate(new User(), 1_500_000);

        self::assertSame(Transaction::RISK_REVIEW, $decision->status);
        self::assertTrue($decision->requiresMfa);
    }

    public function testTooManyPaymentsAreBlocked(): void
    {
        $decision = $this->service(3)->evaluate(new User(), 5_000);

        self::assertTrue($decision->isBlocked());
        self::assertSame(Transaction::RISK_BLOCKED, $decision->status);
    }
}
