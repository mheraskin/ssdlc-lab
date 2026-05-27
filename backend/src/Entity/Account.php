<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\UniqueConstraint(name: 'uniq_accounts_number', columns: ['account_number'])]
class Account
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FROZEN = 'frozen';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accounts')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'account_number', length: 34)]
    private string $accountNumber;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    /** Balance is stored in minor units (cents) to avoid floating-point drift. */
    #[ORM\Column(name: 'balance_cents', type: Types::BIGINT)]
    private int $balanceCents = 0;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): self
    {
        $this->accountNumber = $accountNumber;

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

    public function getBalanceCents(): int
    {
        // Doctrine maps BIGINT to string in PHP; normalise to int.
        return (int) $this->balanceCents;
    }

    public function setBalanceCents(int $balanceCents): self
    {
        $this->balanceCents = $balanceCents;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function debit(int $cents): void
    {
        $this->setBalanceCents($this->getBalanceCents() - $cents);
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
