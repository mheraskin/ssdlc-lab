<?php

namespace App\MessageHandler;

use App\Entity\AuditLog;
use App\Message\PaymentCreatedMessage;
use App\Repository\TransactionRepository;
use App\Service\AuditLogger;
use App\Service\ExternalPaymentGatewayInterface;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reacts to a completed payment: forwards it to the external payment system (mock),
 * notifies the user (mock notification), and writes the completion audit event.
 *
 * This is the asynchronous "fan-out" after a payment, decoupled from the request that
 * created it via the message broker.
 */
#[AsMessageHandler]
final class PaymentCreatedMessageHandler
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly ExternalPaymentGatewayInterface $gateway,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    public function __invoke(PaymentCreatedMessage $message): void
    {
        $transaction = $this->transactions->find($message->transactionId);
        if (null === $transaction) {
            return;
        }

        $result = $this->gateway->submit($transaction);

        $owner = $transaction->getCreatedBy();
        $amount = number_format($transaction->getAmountCents() / 100, 2);
        $this->notifications->notify(
            $owner,
            sprintf(
                'Payment of %s %s to %s was processed successfully (ref %s).',
                $amount,
                $transaction->getCurrency(),
                $transaction->getRecipientName(),
                $result->reference,
            ),
            subject: 'Your payment was processed',
        );

        $this->audit->log(
            AuditLog::PAYMENT_COMPLETED,
            actor: $owner,
            entityType: 'Transaction',
            entityId: (string) $transaction->getId(),
            metadata: [
                'amount_cents' => $transaction->getAmountCents(),
                'currency' => $transaction->getCurrency(),
                'gateway_reference' => $result->reference,
            ],
        );
    }
}
