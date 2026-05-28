<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AuditLogger;
use App\Service\TotpSecretCipher;
use App\Service\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TotpService::class)]
#[CoversClass(TotpSecretCipher::class)]
class TotpServiceTest extends TestCase
{
    private function service(): TotpService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $audit = $this->createStub(AuditLogger::class);
        // Real cipher so encryption/decryption is actually exercised in unit tests.
        $cipher = new TotpSecretCipher('test-app-secret-for-unit-tests');

        return new TotpService($em, $audit, $cipher);
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
        // The stored value must NOT be the plaintext seed — it is encrypted at rest.
        self::assertNotSame($data['secret'], $user->getTotpSecret());
        self::assertNotNull($user->getTotpSecret());
        self::assertStringNotContainsString($data['secret'], (string) $user->getTotpSecret());
        self::assertFalse($user->isTotpEnabled());
    }

    public function testConfirmEnrollmentAcceptsCurrentCodeAndEnables(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);

        $code = $svc->totpFor($user)->now();

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
        $svc->confirmEnrollment($user, $svc->totpFor($user)->now());

        self::assertTrue($svc->verify($user, $svc->totpFor($user)->now()));
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);
        $svc->confirmEnrollment($user, $svc->totpFor($user)->now());

        self::assertFalse($svc->verify($user, '000000'));
    }

    public function testVerifyFailsWhenNotEnrolled(): void
    {
        $user = (new User())->setEmail('me@example.com');
        // user has no secret — verify must refuse rather than throw
        self::assertFalse($this->service()->verify($user, '123456'));
    }

    /**
     * A TOTP code is valid for ~30s, but once accepted it must NOT be replayable within
     * that same window — that would defeat the possession-factor guarantee.
     */
    public function testCodeCannotBeReplayedInTheSameStep(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);
        $svc->confirmEnrollment($user, $svc->totpFor($user)->now());

        $code = $svc->totpFor($user)->now();

        self::assertTrue($svc->verify($user, $code), 'first use accepted');
        self::assertFalse($svc->verify($user, $code), 'same code is replay — must be refused');
    }

    public function testDisableClearsSecretAndFlag(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $svc->startEnrollment($user);
        $svc->confirmEnrollment($user, $svc->totpFor($user)->now());

        $svc->disable($user);

        self::assertNull($user->getTotpSecret());
        self::assertFalse($user->isTotpEnabled());
        self::assertNull($user->getTotpLastUsedCounter());
    }

    /**
     * The stored value really is ciphertext: it neither equals the plaintext seed nor
     * contains it as a substring. (Sanity-check for the encryption-at-rest claim.)
     */
    public function testStoredSecretIsCiphertextNotPlaintext(): void
    {
        $user = (new User())->setEmail('me@example.com');
        $svc = $this->service();
        $data = $svc->startEnrollment($user);

        self::assertNotSame($data['secret'], $user->getTotpSecret());
        self::assertStringNotContainsString($data['secret'], (string) $user->getTotpSecret());
        // Plaintext is base32 (A-Z2-7); ciphertext is base64 — different alphabets.
        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/=]+$#', (string) $user->getTotpSecret());
    }
}
