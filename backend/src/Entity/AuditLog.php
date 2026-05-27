<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only security/business event log (Audit Log layer).
 *
 * Immutability is enforced at the database level by a trigger installed in the
 * migration that blocks UPDATE and DELETE on this table. The application also
 * never exposes update/delete operations for it.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_event_type', columns: ['event_type'])]
#[ORM\Index(name: 'idx_audit_created_at', columns: ['created_at'])]
class AuditLog
{
    // Authentication / session events
    public const LOGIN_SUCCESS = 'login_success';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGOUT = 'logout';
    public const LOGIN_RATE_LIMITED = 'login_rate_limited';
    // Payment events
    public const PAYMENT_CREATED = 'payment_created';
    public const PAYMENT_COMPLETED = 'payment_completed';
    public const PAYMENT_REJECTED = 'payment_rejected';
    public const PAYMENT_MFA_REQUIRED = 'payment_mfa_required';
    // MFA events
    public const MFA_SUCCESS = 'mfa_success';
    public const MFA_FAILED = 'mfa_failed';
    // Admin events
    public const ADMIN_VIEWED_AUDIT_LOGS = 'admin_viewed_audit_logs';
    public const ADMIN_CHANGED_USER_STATUS = 'admin_changed_user_status';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nullable: some events (e.g. failed login for unknown email) have no resolved actor. */
    #[ORM\Column(name: 'actor_user_id', nullable: true)]
    private ?int $actorUserId = null;

    #[ORM\Column(name: 'actor_email', length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(name: 'event_type', length: 64)]
    private string $eventType;

    #[ORM\Column(name: 'entity_type', length: 64, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(name: 'entity_id', length: 64, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(name: 'ip_address', length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', length: 512, nullable: true)]
    private ?string $userAgent = null;

    /** Free-form, non-sensitive context. Never contains passwords, tokens, CVV, etc. */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActorUserId(): ?int
    {
        return $this->actorUserId;
    }

    public function setActorUserId(?int $actorUserId): self
    {
        $this->actorUserId = $actorUserId;

        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): self
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Used by the demo seeder to backdate historical events. */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
