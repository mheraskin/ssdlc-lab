<?php

namespace App\Message;

/**
 * Domain event published after a payment is successfully executed.
 *
 * Represents the "Message Broker / Event Queue" layer: the core payment operation
 * does not directly depend on notification or external-gateway latency — those are
 * handled by the message handler. Locally this runs on the synchronous transport;
 * production would route it to a Doctrine/Redis/RabbitMQ transport with a worker.
 */
final class PaymentCreatedMessage
{
    public function __construct(public readonly int $transactionId)
    {
    }
}
