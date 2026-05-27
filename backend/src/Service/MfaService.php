<?php

namespace App\Service;

use App\Entity\MfaChallenge;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Multi-factor confirmation service.
 *
 * Generates a random one-time code, stores only its hash, and delivers it by email
 * (Postmark in production, Mailpit locally). This is a full MFA flow — there is no fixed
 * code. The same flow could be extended to TOTP or SMS by adding another delivery channel.
 */
class MfaService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppMailer $mailer,
        private readonly LoggerInterface $logger,
        private readonly int $codeTtlSeconds,
    ) {
    }

    public function createChallenge(User $user, string $purpose, ?int $transactionId = null): MfaChallenge
    {
        $code = $this->generateCode();

        $challenge = new MfaChallenge();
        $challenge->setUser($user)
            ->setPurpose($purpose)
            ->setRelatedTransactionId($transactionId)
            ->setExpiresAt(new \DateTimeImmutable("+{$this->codeTtlSeconds} seconds"))
            // Code stored as a hash only — never persisted in clear text.
            ->setCodeHash(password_hash($code, \PASSWORD_BCRYPT));

        $this->em->persist($challenge);
        $this->em->flush();

        // Deliver the code. A delivery failure must not destroy the challenge (the code is
        // recoverable from logs/Mailpit in dev), so we log instead of throwing.
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

        if (!password_verify($code, $challenge->getCodeHash())) {
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
