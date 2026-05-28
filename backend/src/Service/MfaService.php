<?php

namespace App\Service;

use App\Entity\MfaChallenge;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Multi-factor / step-up confirmation orchestration.
 *
 * Each {@see MfaChallenge} carries a factor — `email_otp` (out-of-band step-up; not true
 * MFA on its own) or `totp` (RFC 6238 authenticator-app code; real possession factor).
 * {@see verify()} dispatches verification to the right backend by factor; attempt counting,
 * expiry and lockout are shared.
 */
class MfaService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppMailer $mailer,
        private readonly TotpService $totp,
        private readonly LoggerInterface $logger,
        private readonly int $codeTtlSeconds,
    ) {
    }

    /** Email-OTP step-up: generate a random code, hash it, store it, email the user. */
    public function createChallenge(User $user, string $purpose, ?int $transactionId = null): MfaChallenge
    {
        $code = $this->generateCode();

        $challenge = new MfaChallenge();
        $challenge->setUser($user)
            ->setPurpose($purpose)
            ->setRelatedTransactionId($transactionId)
            ->setFactor(MfaChallenge::FACTOR_EMAIL_OTP)
            ->setExpiresAt(new \DateTimeImmutable("+{$this->codeTtlSeconds} seconds"))
            // Code stored as a hash only — never persisted in clear text.
            ->setCodeHash(password_hash($code, \PASSWORD_BCRYPT));

        $this->em->persist($challenge);
        $this->em->flush();

        try {
            $this->mailer->sendMfaCode($user, $code, $this->codeTtlSeconds);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send MFA code email', [
                'user' => $user->getEmail(),
                'challenge' => $challenge->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $challenge;
    }

    /**
     * TOTP challenge: no code is stored (the code is derived from secret+time on each
     * verification); the row exists purely to track attempts/lockout for this transaction.
     */
    public function createTotpChallenge(User $user, string $purpose, ?int $transactionId = null): MfaChallenge
    {
        $challenge = new MfaChallenge();
        $challenge->setUser($user)
            ->setPurpose($purpose)
            ->setRelatedTransactionId($transactionId)
            ->setFactor(MfaChallenge::FACTOR_TOTP)
            ->setExpiresAt(new \DateTimeImmutable("+{$this->codeTtlSeconds} seconds"))
            ->setCodeHash(null);

        $this->em->persist($challenge);
        $this->em->flush();

        return $challenge;
    }

    public function verify(MfaChallenge $challenge, string $code): bool
    {
        if (MfaChallenge::STATUS_PENDING !== $challenge->getStatus()) {
            return false;
        }

        if ($challenge->isExpired()) {
            $challenge->setStatus(MfaChallenge::STATUS_EXPIRED);
            $this->em->flush();

            return false;
        }

        $challenge->incrementAttempts();

        $valid = match ($challenge->getFactor()) {
            MfaChallenge::FACTOR_TOTP => $this->totp->verify($challenge->getUser(), $code),
            // FACTOR_EMAIL_OTP (default): bcrypt-compare the entered code with the stored hash.
            default => null !== $challenge->getCodeHash()
                && password_verify($code, $challenge->getCodeHash()),
        };

        if (!$valid) {
            if ($challenge->getAttempts() >= self::MAX_ATTEMPTS) {
                $challenge->setStatus(MfaChallenge::STATUS_FAILED);
            }
            $this->em->flush();

            return false;
        }

        $challenge->markVerified();
        $this->em->flush();

        return true;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', \STR_PAD_LEFT);
    }
}
