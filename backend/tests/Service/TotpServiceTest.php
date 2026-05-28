<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TotpService::class)]
class TotpServiceTest extends TestCase
{
    private function service(): TotpService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $audit = $this->createStub(AuditLogger::class);

        return new TotpService($em, $audit);
    }

    public function testStartEnrollmentProducesSecretAndProvisioningUri(): void
    {
        $user = (new User())->setEmail('me@example.com');

        $data = $this->service()->startEnrollment($user);

        self::assertArrayHasKey('secret', $data);
        self::assertArrayHasKey('provisioningUri', $data);
        self::assertNotEmpty($data['secret']);
        self::assertStringStartsWith('otpauth://totp/', $data['provisioningUri']);
        self::assertStringContainsString('issuer=SSDLC%20Bank', $data['provisioningUri']);
        // Secret stored on user; enrollment is NOT yet enabled.
        self::assertSame($data['secret'], $user->getTotpSecret());
        self::assertFalse($user->isTotpEnabled());
    }

    public function testConfirmEnrollmentAcceptsCurrentCodeAndEnables(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);

        $code = TOTP::createFromSecret($user->getTotpSecret())->now();

        self::assertTrue($svc->confirmEnrollment($user, $code));
        self::assertTrue($user->isTotpEnabled());
    }

    public function testConfirmEnrollmentRejectsWrongCode(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);

        self::assertFalse($svc->confirmEnrollment($user, '000000'));
        self::assertFalse($user->isTotpEnabled());
    }

    public function testVerifyAcceptsValidAuthenticatorCodeForEnrolledUser(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);
        $svc->confirmEnrollment($user, TOTP::createFromSecret($user->getTotpSecret())->now());

        $fresh = TOTP::createFromSecret($user->getTotpSecret())->now();

        self::assertTrue($svc->verify($user, $fresh));
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);
        $svc->confirmEnrollment($user, TOTP::createFromSecret($user->getTotpSecret())->now());

        self::assertFalse($svc->verify($user, '000000'));
    }

    public function testVerifyFailsWhenNotEnrolled(): void
    {
        $user = (new User())->setEmail('me@example.com');
        // user has no secret — verify must refuse rather than throw
        self::assertFalse($this->service()->verify($user, '123456'));
    }

    public function testDisableClearsSecretAndFlag(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);
        $svc->confirmEnrollment($user, TOTP::createFromSecret($user->getTotpSecret())->now());

        $svc->disable($user);

        self::assertNull($user->getTotpSecret());
        self::assertFalse($user->isTotpEnabled());
    }
}
