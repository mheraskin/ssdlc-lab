<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\AuditLog;
use App\Entity\MfaChallenge;
use App\Entity\Transaction;
use App\Entity\User;
use App\Message\PaymentCreatedMessage;
use App\Repository\MfaChallengeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Payment Service — the most critical part of the system.
 *
 * ALL trust decisions happen here on the server: account ownership, account status,
 * sufficient balance, risk rules and (for risky payments) MFA confirmation. The client
 * can never change a balance or a payment status directly.
 */
class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RiskService $risk,
        private readonly MfaService $mfa,
        private readonly MfaChallengeRepository $challenges,
        private readonly AuditLogger $audit,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * Create a payment from one of the user's own accounts.
     */
    public function createPayment(
        User $user,
        Account $source,
        string $recipientName,
        string $recipientAccount,
        int $amountCents,
    ): PaymentResult {
        // Defence in depth: re-verify ownership server-side even though the controller
        // already checked it via the voter. Never trust the client's account selection.
        if ($source->getUser()->getId() !== $user->getId()) {
            $this->audit->log(AuditLog::PAYMENT_REJECTED, actor: $user, metadata: [
                'reason' => 'account_not_owned',
                'source_account_id' => $source->getId(),
            ]);

            return PaymentResult::rejected(null, 'You can only pay from your own accounts.');
        }

        if (Account::STATUS_ACTIVE !== $source->getStatus()) {
            return $this->reject($user, $source, $recipientName, $recipientAccount, $amountCents, 'Source account is not active.');
        }

        if ($amountCents <= 0) {
            return $this->reject($user, $source, $recipientName, $recipientAccount, $amountCents, 'Amount must be positive.');
        }

        if ($amountCents > $source->getBalanceCents()) {
            return $this->reject($user, $source, $recipientName, $recipientAccount, $amountCents, 'Insufficient balance.');
        }

        $decision = $this->risk->evaluate($user, $amountCents);

        $transaction = new Transaction();
        $transaction->setSourceAccount($source)
            ->setRecipientName($recipientName)
            ->setRecipientAccount($recipientAccount)
            ->setAmountCents($amountCents)
            ->setCurrency($source->getCurrency())
            ->setCreatedBy($user)
            ->setRiskStatus($decision->status)
            ->setRiskReason($decision->reason);

        if ($decision->isBlocked()) {
            $transaction->setStatus(Transaction::STATUS_REJECTED);
            $this->em->persist($transaction);
            $this->em->flush();

            $this->audit->log(AuditLog::PAYMENT_REJECTED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: [
                'reason' => 'risk_blocked',
                'risk_reason' => $decision->reason,
            ]);

            return PaymentResult::rejected($transaction, (string) $decision->reason);
        }

        if ($decision->requiresMfa) {
            $transaction->setStatus(Transaction::STATUS_PENDING_MFA);
            $this->em->persist($transaction);
            $this->em->flush();

            $challenge = $this->mfa->createChallenge(
                $user,
                MfaChallenge::PURPOSE_PAYMENT_CONFIRM,
                $transaction->getId(),
            );

            $this->audit->log(AuditLog::PAYMENT_MFA_REQUIRED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: [
                'risk_reason' => $decision->reason,
                'mfa_challenge_id' => $challenge->getId(),
            ]);

            return PaymentResult::mfaRequired(
                $transaction,
                'A confirmation code has been emailed to you. Enter it below to complete the payment.',
            );
        }

        // Low-risk: execute immediately.
        $this->execute($transaction);
        $this->audit->log(AuditLog::PAYMENT_CREATED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: [
            'amount_cents' => $amountCents,
            'currency' => $transaction->getCurrency(),
        ]);
        $this->bus->dispatch(new PaymentCreatedMessage((int) $transaction->getId()));

        return PaymentResult::completed($transaction, 'Payment completed.');
    }

    /**
     * Confirm a risky payment after the user supplies their MFA code.
     */
    public function confirmPayment(User $user, Transaction $transaction, string $code): PaymentResult
    {
        if ($transaction->getCreatedBy()->getId() !== $user->getId()) {
            return PaymentResult::rejected($transaction, 'Not your payment.');
        }

        if (Transaction::STATUS_PENDING_MFA !== $transaction->getStatus()) {
            return PaymentResult::rejected($transaction, 'This payment is not awaiting confirmation.');
        }

        $challenge = $this->challenges->findPendingForTransaction((int) $transaction->getId());
        if (null === $challenge) {
            return PaymentResult::rejected($transaction, 'No active confirmation challenge. Please restart the payment.');
        }

        if (!$this->mfa->verify($challenge, $code)) {
            $this->audit->log(AuditLog::MFA_FAILED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: [
                'attempts' => $challenge->getAttempts(),
            ]);

            return PaymentResult::rejected($transaction, 'Invalid or expired confirmation code.');
        }

        $this->audit->log(AuditLog::MFA_SUCCESS, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId());

        // Re-validate balance at confirmation time (it may have changed since creation).
        $source = $transaction->getSourceAccount();
        if ($transaction->getAmountCents() > $source->getBalanceCents()) {
            $transaction->setStatus(Transaction::STATUS_REJECTED);
            $transaction->setRiskReason('Insufficient balance at confirmation.');
            $this->em->flush();
            $this->audit->log(AuditLog::PAYMENT_REJECTED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: ['reason' => 'insufficient_balance_on_confirm']);

            return PaymentResult::rejected($transaction, 'Insufficient balance.');
        }

        $this->execute($transaction);
        $this->audit->log(AuditLog::PAYMENT_CREATED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: [
            'amount_cents' => $transaction->getAmountCents(),
            'currency' => $transaction->getCurrency(),
            'confirmed_via_mfa' => true,
        ]);
        $this->bus->dispatch(new PaymentCreatedMessage((int) $transaction->getId()));

        return PaymentResult::completed($transaction, 'Payment confirmed and completed.');
    }

    private function execute(Transaction $transaction): void
    {
        // persist() is a no-op when the entity is already managed (the MFA-confirm path),
        // and inserts it on the low-risk path where it was built but not yet persisted.
        $this->em->persist($transaction);
        $transaction->getSourceAccount()->debit($transaction->getAmountCents());
        $transaction->markConfirmed();
        $this->em->flush();
    }

    private function reject(
        User $user,
        Account $source,
        string $recipientName,
        string $recipientAccount,
        int $amountCents,
        string $message,
    ): PaymentResult {
        $transaction = new Transaction();
        $transaction->setSourceAccount($source)
            ->setRecipientName($recipientName)
            ->setRecipientAccount($recipientAccount)
            ->setAmountCents(max($amountCents, 0))
            ->setCurrency($source->getCurrency())
            ->setCreatedBy($user)
            ->setStatus(Transaction::STATUS_REJECTED)
            ->setRiskStatus(Transaction::RISK_OK)
            ->setRiskReason($message);
        $this->em->persist($transaction);
        $this->em->flush();

        $this->audit->log(AuditLog::PAYMENT_REJECTED, actor: $user, entityType: 'Transaction', entityId: (string) $transaction->getId(), metadata: [
            'reason' => $message,
        ]);

        return PaymentResult::rejected($transaction, $message);
    }
}
