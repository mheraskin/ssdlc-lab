<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    public const STATUS_PENDING_MFA = 'pending_mfa';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    public const RISK_OK = 'ok';
    public const RISK_REVIEW = 'review';
    public const RISK_BLOCKED = 'blocked';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'source_account_id', nullable: false)]
    private Account $sourceAccount;

    #[ORM\Column(name: 'recipient_name', length: 255)]
    private string $recipientName;

    #[ORM\Column(name: 'recipient_account', length: 34)]
    private string $recipientAccount;

    /** Amount in minor units (cents). */
    #[ORM\Column(name: 'amount_cents', type: Types::BIGINT)]
    private int $amountCents;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING_MFA;

    #[ORM\Column(name: 'risk_status', length: 20)]
    private string $riskStatus = self::RISK_OK;

    #[ORM\Column(name: 'risk_reason', length: 255, nullable: true)]
    private ?string $riskReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceAccount(): Account
    {
        return $this->sourceAccount;
    }

    public function setSourceAccount(Account $sourceAccount): self
    {
        $this->sourceAccount = $sourceAccount;

        return $this;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): self
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getRecipientAccount(): string
    {
        return $this->recipientAccount;
    }

    public function setRecipientAccount(string $recipientAccount): self
    {
        $this->recipientAccount = $recipientAccount;

        return $this;
    }

    public function getAmountCents(): int
    {
        return (int) $this->amountCents;
    }

    public function setAmountCents(int $amountCents): self
    {
        $this->amountCents = $amountCents;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

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

    public function getRiskStatus(): string
    {
        return $this->riskStatus;
    }

    public function setRiskStatus(string $riskStatus): self
    {
        $this->riskStatus = $riskStatus;

        return $this;
    }

    public function getRiskReason(): ?string
    {
        return $this->riskReason;
    }

    public function setRiskReason(?string $riskReason): self
    {
        $this->riskReason = $riskReason;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Used by the demo seeder to backdate historical transactions. */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function markConfirmed(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->confirmedAt = new \DateTimeImmutable();

        return $this;
    }
}
