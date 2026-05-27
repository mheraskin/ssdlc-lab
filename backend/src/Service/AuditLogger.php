<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Central writer for the immutable audit log (Audit / SIEM layer).
 *
 * Every entry is also mirrored to the Monolog "security" channel so the same events
 * are available to a real SIEM (Datadog / ELK / DO logs) in production — "SIEM-ready".
 *
 * IMPORTANT: callers must never pass passwords, tokens, full card numbers or CVV in
 * the metadata. Only non-sensitive context belongs in the audit trail.
 */
class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $securityLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $eventType,
        ?User $actor = null,
        ?string $actorEmail = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
    ): AuditLog {
        $request = $this->requestStack->getCurrentRequest();

        $entry = new AuditLog();
        $entry->setEventType($eventType)
            ->setActorUserId($actor?->getId())
            ->setActorEmail($actorEmail ?? $actor?->getEmail())
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setIpAddress($request?->getClientIp())
            ->setUserAgent($request ? substr((string) $request->headers->get('User-Agent'), 0, 512) : null)
            ->setMetadata($metadata);

        $this->em->persist($entry);
        $this->em->flush();

        $this->securityLogger->info('audit.'.$eventType, [
            'actor' => $entry->getActorEmail(),
            'entity' => $entityType ? $entityType.'#'.$entityId : null,
            'ip' => $entry->getIpAddress(),
            'metadata' => $metadata,
        ]);

        return $entry;
    }
}
