<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Thin wrapper around Symfony Mailer used by the Auth/MFA and Notification services.
 *
 * Transport is configured via MAILER_DSN:
 *   - locally   → smtp://mailpit:1025  (captured + viewable at http://localhost:8025)
 *   - production → postmark+api://<token>@default  (real delivery via Postmark)
 *
 * The same code path delivers in both environments — only the DSN changes.
 */
class AppMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $from,
    ) {
    }

    public function sendMfaCode(User $user, string $code, int $ttlSeconds): void
    {
        $minutes = max(1, (int) round($ttlSeconds / 60));
        $html = $this->layout(
            'Підтвердьте свій платіж',
            "<p>Вітаємо, {$this->escape($user->getFullName())}!</p>"
            ."<p>Використайте наведений код, щоб підтвердити свій платіж. Він діє {$minutes} хв.</p>"
            ."<p style=\"font-size:30px;font-weight:700;letter-spacing:6px;margin:24px 0;color:#0f2d52\">{$this->escape($code)}</p>"
            .'<p style="color:#5a6b80;font-size:13px">Якщо ви не запитували цей код, негайно зверніться до служби підтримки.</p>'
        );

        $email = (new Email())
            ->from(Address::create($this->from))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Ваш код підтвердження Банку SSDLC')
            ->text("Ваш код підтвердження: {$code}. Діє {$minutes} хв.")
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendNotification(User $user, string $subject, string $message): void
    {
        $email = (new Email())
            ->from(Address::create($this->from))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject($subject)
            ->text($message)
            ->html($this->layout($subject, '<p>'.$this->escape($message).'</p>'));

        $this->mailer->send($email);
    }

    private function layout(string $heading, string $bodyHtml): string
    {
        return '<div style="font-family:system-ui,Segoe UI,Roboto,sans-serif;max-width:480px;margin:auto;'
            .'border:1px solid #e6ebf3;border-radius:12px;overflow:hidden">'
            .'<div style="background:#0f2d52;color:#fff;padding:16px 24px;font-weight:700">🏦 Банк SSDLC</div>'
            .'<div style="padding:24px"><h2 style="margin:0 0 12px;font-size:18px">'.$this->escape($heading).'</h2>'
            .$bodyHtml.'</div></div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }
}
