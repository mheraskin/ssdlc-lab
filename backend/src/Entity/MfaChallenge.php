<?php

namespace App\Entity;

use App\Repository\MfaChallengeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A multi-factor confirmation challenge. Used to gate confirmation of risky payments.
 * The code itself is stored only as a hash (never plain text), mirroring how a real
 * MFA provider would never persist the delivered code in clear.
 */
#[ORM\Entity(repositoryClass: MfaChallengeRepository::class)]
#[ORM\Table(name: 'mfa_challenges')]
class MfaChallenge
{
    public const PURPOSE_PAYMENT_CONFIRM = 'payment_confirm';

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    /** Email one-time code — out-of-band step-up confirmation (NOT true MFA on its own). */
    public const FACTOR_EMAIL_OTP = 'email_otp';
    /** TOTP from an authenticator app (RFC 6238) — possession factor, real MFA with password. */
    public const FACTOR_TOTP = 'totp';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $purpose;

    #[ORM\Column(name: 'related_transaction_id', nullable: true)]
    private ?int $relatedTransactionId = null;

    /** Stored only for email-OTP challenges. NULL for TOTP, where the code is derived from secret+time. */
    #[ORM\Column(name: 'code_hash', nullable: true)]
    private ?string $codeHash = null;

    /** Which factor this challenge expects at confirm time (email_otp / totp). */
    #[ORM\Column(length: 20, options: ['default' => self::FACTOR_EMAIL_OTP])]
    private string $factor = self::FACTOR_EMAIL_OTP;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'attempts', type: Types::SMALLINT)]
    private int $attempts = 0;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'verified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+5 minutes');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getRelatedTransactionId(): ?int
    {
        return $this->relatedTransactionId;
    }

    public function setRelatedTransactionId(?int $relatedTransactionId): self
    {
        $this->relatedTransactionId = $relatedTransactionId;

        return $this;
    }

    public function getCodeHash(): ?string
    {
        return $this->codeHash;
    }

    public function setCodeHash(?string $codeHash): self
    {
        $this->codeHash = $codeHash;

        return $this;
    }

    public function getFactor(): string
    {
        return $this->factor;
    }

    public function setFactor(string $factor): self
    {
        $this->factor = $factor;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): self
    {
        ++$this->attempts;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function markVerified(): self
    {
        $this->status = self::STATUS_VERIFIED;
        $this->verifiedAt = new \DateTimeImmutable();

        return $this;
    }
}
