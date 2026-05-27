<?php

namespace App\Tests\Functional;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Tests\ApiTestCase;

class AccountApiTest extends ApiTestCase
{
    private function makeTransaction(Account $source, User $by, string $recipient): void
    {
        $t = (new Transaction())
            ->setSourceAccount($source)
            ->setCreatedBy($by)
            ->setRecipientName($recipient)
            ->setRecipientAccount('UA00 1111 2222')
            ->setAmountCents(5000)
            ->setCurrency('EUR')
            ->setStatus(Transaction::STATUS_COMPLETED)
            ->setRiskStatus(Transaction::RISK_OK);
        $em = $this->em();
        $em->persist($t);
        $em->flush();
    }

    public function testAccountsRequireAuthentication(): void
    {
        self::assertSame(401, $this->api('GET', '/api/accounts')->getStatusCode());
    }

    /**
     * Security (IDOR / isolation): a client must see ONLY their own accounts, never another
     * user's — even though both exist in the same table.
     */
    public function testUserSeesOnlyOwnAccounts(): void
    {
        $client = $this->makeUser('client@example.com');
        $this->makeAccount($client, 'UA13 0000 1111');
        $this->makeAccount($client, 'UA13 0000 2222');

        $other = $this->makeUser('client2@example.com');
        $this->makeAccount($other, 'UA13 0000 9999');

        $body = $this->json($this->api('GET', '/api/accounts', null, $client));

        $numbers = array_column($body['accounts'], 'accountNumber');
        self::assertCount(2, $numbers);
        self::assertContains('UA13 0000 1111', $numbers);
        self::assertNotContains('UA13 0000 9999', $numbers, 'must not see another user\'s account');
    }

    public function testTransactionsAreScopedToTheAuthenticatedUser(): void
    {
        $client = $this->makeUser('client@example.com');
        $clientAcc = $this->makeAccount($client, 'UA13 0000 1111');
        $this->makeTransaction($clientAcc, $client, 'Clara Recipient');

        $other = $this->makeUser('client2@example.com');
        $otherAcc = $this->makeAccount($other, 'UA13 0000 9999');
        $this->makeTransaction($otherAcc, $other, 'Carl Recipient');

        $body = $this->json($this->api('GET', '/api/transactions', null, $client));

        $recipients = array_column($body['transactions'], 'recipientName');
        self::assertContains('Clara Recipient', $recipients);
        self::assertNotContains('Carl Recipient', $recipients);
    }
}
