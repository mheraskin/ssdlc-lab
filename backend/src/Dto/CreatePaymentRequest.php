<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input contract for creating a payment. Symfony Validator constraints provide the
 * server-side input validation control (the client cannot bypass these).
 */
class CreatePaymentRequest
{
    #[Assert\NotNull(message: 'A source account is required.')]
    #[Assert\Positive]
    public ?int $sourceAccountId = null;

    #[Assert\NotBlank(message: 'Recipient name is required.')]
    #[Assert\Length(max: 255)]
    public string $recipientName = '';

    #[Assert\NotBlank(message: 'Recipient account is required.')]
    #[Assert\Length(min: 4, max: 34)]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9 ]+$/', message: 'Recipient account contains invalid characters.')]
    public string $recipientAccount = '';

    /** Amount in major units (e.g. EUR), validated as a positive number with up to 2 decimals. */
    #[Assert\NotNull(message: 'Amount is required.')]
    #[Assert\Positive(message: 'Amount must be positive.')]
    #[Assert\LessThanOrEqual(value: 1000000, message: 'Amount exceeds the allowed maximum.')]
    public ?float $amount = null;

    public function amountInCents(): int
    {
        return (int) round((float) $this->amount * 100);
    }
}
