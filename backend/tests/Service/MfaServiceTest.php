<?php

namespace App\Tests\Service;

use App\Entity\MfaChallenge;
use App\Entity\User;
use App\Service\AppMailer;
use App\Service\MfaService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(MfaService::class)]
class MfaServiceTest extends TestCase
{
    private function service(?AppMailer $mailer = null): MfaService
    {
        return new MfaService(
            $this->createStub(EntityManagerInterface::class),
            $mailer ?? $this->createStub(AppMailer::class),
            new NullLogger(),
            300,
        );
    }

    /** A pending challenge whose code is `$code` (stored only as a hash). */
    private function pendingChallenge(string $code, string $when = '+5 minutes'): MfaChallenge
    {
        $c = new MfaChallenge();
        $c->setUser(new User())
            ->setPurpose(MfaChallenge::PURPOSE_PAYMENT_CONFIRM)
            ->setCodeHash(password_hash($code, \PASSWORD_BCRYPT))
            ->setExpiresAt(new \DateTimeImmutable($when));

        return $c;
    }

    public function testCreateChallengeStoresHashedCodeAndEmailsIt(): void
    {
        $sentCode = null;
        $mailer = $this->createMock(AppMailer::class);
        $mailer->expects(self::once())
            ->method('sendMfaCode')
            ->willReturnCallback(function (User $u, string $code, int $ttl) use (&$sentCode): void {
                $sentCode = $code;
            });

        $challenge = $this->service($mailer)->createChallenge(new User(), MfaChallenge::PURPOSE_PAYMENT_CONFIRM, 42);

        self::assertNotNull($sentCode);
        self::assertMatchesRegularExpression('/^\d{6}$/', $sentCode, 'a 6-digit numeric code is emailed');
        // The stored value is a hash, never the plaintext code.
        self::assertNotSame($sentCode, $challenge->getCodeHash());
        self::assertTrue(password_verify($sentCode, $challenge->getCodeHash()), 'emailed code matches the stored hash');
        self::assertSame(42, $challenge->getRelatedTransactionId());
    }

    public function testVerifySucceedsWithCorrectCode(): void
    {
        $challenge = $this->pendingChallenge('123456');
        self::assertTrue($this->service()->verify($challenge, '123456'));
        self::assertSame(MfaChallenge::STATUS_VERIFIED, $challenge->getStatus());
        self::assertNotNull($challenge->getVerifiedAt());
    }

    public function testVerifyFailsWithWrongCode(): void
    {
        $challenge = $this->pendingChallenge('123456');
        self::assertFalse($this->service()->verify($challenge, '000000'));
        self::assertSame(MfaChallenge::STATUS_PENDING, $challenge->getStatus());
        self::assertSame(1, $challenge->getAttempts());
    }

    public function testVerifyFailsWhenExpired(): void
    {
        $challenge = $this->pendingChallenge('123456', '-1 minute');
        self::assertFalse($this->service()->verify($challenge, '123456'));
        self::assertSame(MfaChallenge::STATUS_EXPIRED, $challenge->getStatus());
    }

    public function testChallengeLocksAfterTooManyWrongAttempts(): void
    {
        $service = $this->service();
        $challenge = $this->pendingChallenge('123456');

        for ($i = 0; $i < 5; ++$i) {
            self::assertFalse($service->verify($challenge, '999999'));
        }

        self::assertSame(MfaChallenge::STATUS_FAILED, $challenge->getStatus());
        // Even the correct code is now rejected because the challenge is locked.
        self::assertFalse($service->verify($challenge, '123456'));
    }

    public function testVerifyFailsWhenNotPending(): void
    {
        $challenge = $this->pendingChallenge('123456');
        $challenge->markVerified();
        self::assertFalse($this->service()->verify($challenge, '123456'));
    }
}
