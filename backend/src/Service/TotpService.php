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
 * Hardening built in:
 *   - The TOTP seed is encrypted at rest by {@see TotpSecretCipher} — a DB dump alone
 *     does not yield usable secrets.
 *   - Intra-step replay protection: each verification advances the user's
 *     last-used step counter; the same code cannot be accepted twice.
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
        private readonly TotpSecretCipher $cipher,
    ) {
    }

    /**
     * Begin enrollment: generate a new TOTP secret, store its ciphertext on the user
     * (not yet enabled), and return the plaintext secret + `otpauth://` provisioning URI
     * (shown ONCE to the user/QR — never stored in plaintext).
     *
     * @return array{secret: string, provisioningUri: string}
     */
    public function startEnrollment(User $user): array
    {
        $totp = TOTP::generate();
        $totp->setLabel($user->getEmail());
        $totp->setIssuer(self::ISSUER);
        $plainSecret = $totp->getSecret();

        $user->setTotpSecret($this->cipher->encrypt($plainSecret));
        $user->setTotpEnabled(false);
        $user->setTotpLastUsedCounter(null);
        $this->em->flush();

        $this->audit->log(AuditLog::TOTP_ENROLLMENT_STARTED, actor: $user);

        return [
            'secret' => $plainSecret,
            'provisioningUri' => $totp->getProvisioningUri(),
        ];
    }

    /**
     * Confirm enrollment by validating the first code from the user's authenticator app.
     * Enrollment binding is not subject to replay tracking — the same code may be used
     * again at a later /verify (separate sliding window), but {@see verify()} will then
     * advance the replay counter.
     */
    public function confirmEnrollment(User $user, string $code): bool
    {
        if (null === $user->getTotpSecret() || $user->isTotpEnabled()) {
            return false;
        }

        $totp = $this->totpFor($user);
        if (!$totp->verify($code, null, self::LEEWAY_STEPS)) {
            return false;
        }

        $user->setTotpEnabled(true);
        $this->em->flush();
        $this->audit->log(AuditLog::TOTP_ENROLLED, actor: $user);

        return true;
    }

    /**
     * Verify a code from the authenticator app for an already-enrolled user.
     *
     * Walks the leeway window manually so we can apply replay protection: a counter
     * already accepted in the past is refused even if the code is currently within the
     * leeway window. On success the user's last-used counter advances.
     */
    public function verify(User $user, string $code): bool
    {
        if (!$user->isTotpEnabled() || null === $user->getTotpSecret()) {
            return false;
        }

        $totp = $this->totpFor($user);
        $period = $totp->getPeriod();
        $currentCounter = intdiv(time(), $period);
        $lastUsed = $user->getTotpLastUsedCounter() ?? -1;

        for ($offset = -self::LEEWAY_STEPS; $offset <= self::LEEWAY_STEPS; ++$offset) {
            $counter = $currentCounter + $offset;
            if ($counter <= $lastUsed) {
                continue; // replay: this code (or an earlier one) has already been used
            }
            $expected = $totp->at($counter * $period);
            if (hash_equals($expected, $code)) {
                $user->setTotpLastUsedCounter($counter);
                $this->em->flush();

                return true;
            }
        }

        return false;
    }

    /** Turn TOTP off and clear the secret. Caller must have re-authenticated. */
    public function disable(User $user): void
    {
        $user->setTotpSecret(null);
        $user->setTotpEnabled(false);
        $user->setTotpLastUsedCounter(null);
        $this->em->flush();
        $this->audit->log(AuditLog::TOTP_DISABLED, actor: $user);
    }

    /** Build a TOTP instance from the user's encrypted secret (decrypts in memory). */
    public function totpFor(User $user): TOTP
    {
        $encoded = $user->getTotpSecret();
        if (null === $encoded) {
            throw new \LogicException('User has no TOTP secret.');
        }

        return TOTP::createFromSecret($this->cipher->decrypt($encoded));
    }
}
