<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use Symfony\Component\Mime\Email;

class PaymentApiTest extends ApiTestCase
{
    public function testPaymentRequiresAuthentication(): void
    {
        $res = $this->api('POST', '/api/payments', ['sourceAccountId' => 1, 'recipientName' => 'X', 'recipientAccount' => 'UA00', 'amount' => 10]);
        self::assertSame(401, $res->getStatusCode());
    }

    public function testLowRiskPaymentCompletesAndDebitsBalance(): void
    {
        $client = $this->makeUser('client@example.com');
        $account = $this->makeAccount($client, 'UA13 0000 1111', 100_000); // 1000.00

        $res = $this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(),
            'recipientName' => 'Corner Shop',
            'recipientAccount' => 'UA10 2030',
            'amount' => 50.00,
        ], $client);

        self::assertSame(201, $res->getStatusCode());
        self::assertSame('completed', $this->json($res)['status']);

        // Balance is controlled server-side: 1000.00 - 50.00 = 950.00.
        $accounts = $this->json($this->api('GET', '/api/accounts', null, $client))['accounts'];
        self::assertSame('950.00', $accounts[0]['balance']);
    }

    /**
     * Security: a client cannot pay FROM an account they do not own (AccountVoter).
     */
    public function testCannotPayFromAnotherUsersAccount(): void
    {
        $client = $this->makeUser('client@example.com');
        $other = $this->makeUser('client2@example.com');
        $victimAccount = $this->makeAccount($other, 'UA13 0000 9999', 500_000);

        $res = $this->api('POST', '/api/payments', [
            'sourceAccountId' => $victimAccount->getId(),
            'recipientName' => 'Attacker',
            'recipientAccount' => 'UA66 6666',
            'amount' => 100.00,
        ], $client);

        self::assertSame(403, $res->getStatusCode());
    }

    public function testPaymentExceedingBalanceIsRejected(): void
    {
        $client = $this->makeUser('client@example.com');
        $account = $this->makeAccount($client, 'UA13 0000 1111', 100_000); // 1000.00

        $res = $this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(),
            'recipientName' => 'Too Much',
            'recipientAccount' => 'UA10 2030',
            'amount' => 5000.00,
        ], $client);

        self::assertSame(422, $res->getStatusCode());
        self::assertSame('rejected', $this->json($res)['status']);
    }

    public function testInvalidPayloadIsRejectedByValidation(): void
    {
        $client = $this->makeUser('client@example.com');
        $account = $this->makeAccount($client, 'UA13 0000 1111', 100_000);

        $res = $this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(),
            'recipientName' => '',
            'recipientAccount' => 'UA10 2030',
            'amount' => -10,
        ], $client);

        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('fields', $this->json($res));
    }

    /**
     * Full MFA flow: a high-value payment is held pending, a one-time code is EMAILED (not
     * returned in the response), and the payment only completes once that code is supplied.
     */
    public function testHighValuePaymentRequiresEmailedMfaCodeToComplete(): void
    {
        $client = $this->makeUser('client@example.com');
        $account = $this->makeAccount($client, 'UA13 0000 1111', 2_000_000); // 20,000.00

        $create = $this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(),
            'recipientName' => 'Auto Dealer',
            'recipientAccount' => 'UA77 6655',
            'amount' => 11_000,
        ], $client);

        self::assertSame(202, $create->getStatusCode());
        $body = $this->json($create);
        self::assertSame('mfa_required', $body['status']);
        self::assertSame('pending_mfa', $body['transaction']['status']);
        // Security: the code must NOT be in the API response.
        self::assertArrayNotHasKey('mockMfaCode', $body);
        self::assertArrayNotHasKey('code', $body);
        $txId = $body['transaction']['id'];

        // The code is delivered by email — pull it out of the captured message.
        $code = $this->extractMfaCodeFromEmail();
        self::assertNotNull($code, 'an MFA code email was sent');

        // Wrong code is rejected...
        $bad = $this->api('POST', "/api/payments/{$txId}/confirm", ['code' => '000000'], $client);
        self::assertSame(422, $bad->getStatusCode());

        // ...the emailed code completes the payment and debits the balance.
        $ok = $this->api('POST', "/api/payments/{$txId}/confirm", ['code' => $code], $client);
        self::assertSame(201, $ok->getStatusCode());
        self::assertSame('completed', $this->json($ok)['status']);

        $accounts = $this->json($this->api('GET', '/api/accounts', null, $client))['accounts'];
        self::assertSame('9000.00', $accounts[0]['balance']); // 20,000 - 11,000
    }

    private function extractMfaCodeFromEmail(): ?string
    {
        foreach ($this->getMailerMessages() as $message) {
            if ($message instanceof Email && str_contains((string) $message->getSubject(), 'confirmation code')) {
                if (preg_match('/\b(\d{6})\b/', (string) $message->getTextBody(), $m)) {
                    return $m[1];
                }
            }
        }

        return null;
    }
}
