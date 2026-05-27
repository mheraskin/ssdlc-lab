<?php

namespace App\Tests\Dto;

use App\Dto\CreatePaymentRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(CreatePaymentRequest::class)]
class CreatePaymentRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function valid(): CreatePaymentRequest
    {
        $dto = new CreatePaymentRequest();
        $dto->sourceAccountId = 1;
        $dto->recipientName = 'Acme Corp';
        $dto->recipientAccount = 'UA12 3456 7890';
        $dto->amount = 100.50;

        return $dto;
    }

    public function testValidRequestHasNoViolations(): void
    {
        self::assertCount(0, $this->validator->validate($this->valid()));
    }

    /**
     * Money is handled in integer cents to avoid float drift.
     */
    public function testAmountIsConvertedToCents(): void
    {
        $dto = $this->valid();
        $dto->amount = 100.50;
        self::assertSame(10050, $dto->amountInCents());

        $dto->amount = 0.01;
        self::assertSame(1, $dto->amountInCents());

        $dto->amount = 11000;
        self::assertSame(1_100_000, $dto->amountInCents());
    }

    public function testBlankRecipientNameIsRejected(): void
    {
        $dto = $this->valid();
        $dto->recipientName = '';
        self::assertGreaterThan(0, $this->validator->validate($dto)->count());
    }

    public function testNonPositiveAmountIsRejected(): void
    {
        $dto = $this->valid();
        $dto->amount = -5;
        self::assertGreaterThan(0, $this->validator->validate($dto)->count());

        $dto->amount = 0;
        self::assertGreaterThan(0, $this->validator->validate($dto)->count());
    }

    public function testAmountOverMaximumIsRejected(): void
    {
        $dto = $this->valid();
        $dto->amount = 1_000_000.01;
        self::assertGreaterThan(0, $this->validator->validate($dto)->count());
    }

    /**
     * Security: the recipient account is constrained to a safe character set, rejecting
     * injection-style payloads at the input boundary.
     */
    public function testRecipientAccountRejectsUnsafeCharacters(): void
    {
        $dto = $this->valid();
        $dto->recipientAccount = "UA12'; DROP TABLE accounts;--";
        self::assertGreaterThan(0, $this->validator->validate($dto)->count());

        $dto->recipientAccount = '<script>alert(1)</script>';
        self::assertGreaterThan(0, $this->validator->validate($dto)->count());
    }
}
