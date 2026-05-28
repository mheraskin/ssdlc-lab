<?php

namespace App\Api;

use App\Entity\Account;
use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Transaction;
use App\Entity\User;

/**
 * Shapes entities into API arrays. Centralising this guarantees that sensitive fields
 * (password hashes, MFA code hashes) are NEVER serialised into a response.
 */
final class EntityPresenter
{
    public static function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'status' => $user->getStatus(),
            'totpEnabled' => $user->isTotpEnabled(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    public static function account(Account $account): array
    {
        return [
            'id' => $account->getId(),
            'accountNumber' => $account->getAccountNumber(),
            'currency' => $account->getCurrency(),
            'balanceCents' => $account->getBalanceCents(),
            'balance' => number_format($account->getBalanceCents() / 100, 2, '.', ''),
            'status' => $account->getStatus(),
            'ownerEmail' => $account->getUser()->getEmail(),
        ];
    }

    public static function transaction(Transaction $t): array
    {
        return [
            'id' => $t->getId(),
            'sourceAccountId' => $t->getSourceAccount()->getId(),
            'sourceAccountNumber' => $t->getSourceAccount()->getAccountNumber(),
            'recipientName' => $t->getRecipientName(),
            'recipientAccount' => $t->getRecipientAccount(),
            'amountCents' => $t->getAmountCents(),
            'amount' => number_format($t->getAmountCents() / 100, 2, '.', ''),
            'currency' => $t->getCurrency(),
            'status' => $t->getStatus(),
            'riskStatus' => $t->getRiskStatus(),
            'riskReason' => $t->getRiskReason(),
            'createdBy' => $t->getCreatedBy()->getEmail(),
            'createdAt' => $t->getCreatedAt()->format(\DATE_ATOM),
            'confirmedAt' => $t->getConfirmedAt()?->format(\DATE_ATOM),
        ];
    }

    public static function auditLog(AuditLog $a): array
    {
        return [
            'id' => $a->getId(),
            'eventType' => $a->getEventType(),
            'actorEmail' => $a->getActorEmail(),
            'entityType' => $a->getEntityType(),
            'entityId' => $a->getEntityId(),
            'ipAddress' => $a->getIpAddress(),
            'metadata' => $a->getMetadata(),
            'createdAt' => $a->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    public static function notification(Notification $n): array
    {
        return [
            'id' => $n->getId(),
            'type' => $n->getType(),
            'recipient' => $n->getRecipient(),
            'message' => $n->getMessage(),
            'status' => $n->getStatus(),
            'createdAt' => $n->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
