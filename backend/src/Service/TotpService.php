<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;

/**
 * TOTP-based MFA (RFC 6238) — the real second factor.
 *
 * The user's authenticator app (Google Authenticator, 1Password, Authy, …) stores the
 * shared secret and produces a 6-digit code every 30 s; we recompute and compare it on
 * the server. Combined with the password (knowledge), this is a true MFA pair
 * (NIST SP 800-63B AAL2 possession factor).
 *
 * "Google Authenticator" is just one client of this open protocol — there is no Google
 * API or SDK involved.
 */
class TotpService
{
    private const ISSUER = 'SSDLC Bank';
    /** Accept the current step and ±1 (≈ ±30 s) to absorb clock drift. */
    private const LEEWAY_STEPS = 1;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Begin enrollment: generate a new TOTP secret, store it on the user (not yet enabled),
     * and return the secret + `otpauth://` provisioning URI for the QR.
     *
     * @return array{secret: string, provisioningUri: string}
     */
    public function startEnrollment(User $user): array
    {
        $totp = TOTP::generate();
        $totp->setLabel($user->getEmail());
        $totp->setIssuer(self::ISSUER);

        $user->setTotpSecret($totp->getSecret());
        $user->setTotpEnabled(false);
        $this->em->flush();

        $this->audit->log(AuditLog::TOTP_ENROLLMENT_STARTED, actor: $user);

        return [
            'secret' => $totp->getSecret(),
            'provisioningUri' => $totp->getProvisioningUri(),
        ];
    }

    /**
     * Confirm enrollment by validating the first code from the user's authenticator app.
     * On success the second factor is activated for that user.
     */
    public function confirmEnrollment(User $user, string $code): bool
    {
        if (null === $user->getTotpSecret() || $user->isTotpEnabled()) {
            return false;
        }
        if (!$this->totpFor($user)->verify($code, null, self::LEEWAY_STEPS)) {
            return false;
        }

        $user->setTotpEnabled(true);
        $this->em->flush();
        $this->audit->log(AuditLog::TOTP_ENROLLED, actor: $user);

        return true;
    }

    /** Verify a code from the authenticator app for an already-enrolled user. */
    public function verify(User $user, string $code): bool
    {
        if (!$user->isTotpEnabled() || null === $user->getTotpSecret()) {
            return false;
        }

        return $this->totpFor($user)->verify($code, null, self::LEEWAY_STEPS);
    }

    /** Turn TOTP off and clear the secret. Caller must have re-authenticated. */
    public function disable(User $user): void
    {
        $user->setTotpSecret(null);
        $user->setTotpEnabled(false);
        $this->em->flush();
        $this->audit->log(AuditLog::TOTP_DISABLED, actor: $user);
    }

    /** Build a TOTP instance from the user's stored secret. */
    public function totpFor(User $user): TOTP
    {
        $secret = $user->getTotpSecret();
        if (null === $secret) {
            throw new \LogicException('User has no TOTP secret.');
        }

        return TOTP::createFromSecret($secret);
    }
}
