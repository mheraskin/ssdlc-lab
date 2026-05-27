<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Notification Service. Persists a notification record AND delivers it for real over the
 * configured channel. Email notifications go through the same mailer as MFA (Postmark in
 * production, Mailpit locally). SMS/Push would plug in here as additional channels.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AppMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(
        User $user,
        string $message,
        string $type = Notification::TYPE_EMAIL,
        string $subject = 'SSDLC Bank notification',
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user)
            ->setType($type)
            ->setRecipient($user->getEmail())
            ->setMessage($message)
            ->setStatus(Notification::STATUS_QUEUED);

        // Deliver email notifications for real; other channels remain mocked for the lab.
        if (Notification::TYPE_EMAIL === $type) {
            try {
                $this->mailer->sendNotification($user, $subject, $message);
                $notification->setStatus(Notification::STATUS_SENT);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send notification email', [
                    'user' => $user->getEmail(),
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // Mock channels are recorded as sent.
            $notification->setStatus(Notification::STATUS_SENT);
        }

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }
}
