<?php

namespace App\Command;

use App\Entity\Account;
use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds demo users, accounts, transactions, notifications and audit history.
 * Idempotent: does nothing if the demo client already exists.
 */
#[AsCommand(name: 'app:load-demo-data', description: 'Seed demo users, accounts and transactions')]
class LoadDemoDataCommand extends Command
{
    private const PASSWORD = 'Password123!';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null !== $this->users->findOneByEmail('client@example.com')) {
            $io->info('Demo data already present — skipping.');

            return Command::SUCCESS;
        }

        // --- Users -----------------------------------------------------------------
        $admin = $this->makeUser('admin@example.com', 'Alice Admin', ['ROLE_ADMIN']);
        $employee = $this->makeUser('employee@example.com', 'Eddie Employee', ['ROLE_EMPLOYEE']);
        $client = $this->makeUser('client@example.com', 'Clara Client', ['ROLE_CLIENT']);
        $client2 = $this->makeUser('client2@example.com', 'Carl Customer', ['ROLE_CLIENT']);
        $blocked = $this->makeUser('victor@example.com', 'Victor Voss', ['ROLE_CLIENT'], User::STATUS_BLOCKED);

        // --- Accounts --------------------------------------------------------------
        $checking = $this->makeAccount($client, 'UA13 0000 1111 2222', 'EUR', 524_318);     // 5,243.18
        $savings = $this->makeAccount($client, 'UA13 0000 3333 4444', 'EUR', 1_284_900);    // 12,849.00
        $usd = $this->makeAccount($client, 'UA13 0000 7777 8888', 'USD', 320_000);          // 3,200.00
        $carlEur = $this->makeAccount($client2, 'UA13 0000 5555 6666', 'EUR', 300_000);     // 3,000.00 (other user)
        $this->makeAccount($client2, 'UA13 0000 9999 0000', 'GBP', 150_000);                // 1,500.00
        $this->makeAccount($blocked, 'UA13 0000 1212 3434', 'EUR', 80_000);                 // 800.00

        // --- Transaction history for Clara -----------------------------------------
        $t = [];
        $t[] = $this->makeTransaction($checking, $client, 'Landlord Properties', 'UA99 8888 7777', 75_000, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-12 days');
        $t[] = $this->makeTransaction($savings, $client, 'Coffee Roasters Ltd', 'UA55 4444 3333', 1_850, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-9 days');
        $t[] = $this->makeTransaction($checking, $client, 'GreenGrocer Market', 'UA22 1111 0000', 6_420, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-6 days');
        $t[] = $this->makeTransaction($usd, $client, 'Cloud Hosting Inc', 'US12 3456 7890', 4_999, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-4 days');
        // A high-value one that was confirmed via MFA.
        $mfaTx = $this->makeTransaction($savings, $client, 'Auto Dealership', 'UA77 6655 4433', 1_150_000, Transaction::STATUS_COMPLETED, Transaction::RISK_REVIEW, '-3 days');
        $mfaTx->setRiskReason('High-value payment (>= 10000.00) requires additional confirmation.');
        $t[] = $mfaTx;
        // One rejected by the risk engine.
        $rej = $this->makeTransaction($checking, $client, 'Unknown Recipient', 'XX00 0000 0000', 990_000, Transaction::STATUS_REJECTED, Transaction::RISK_BLOCKED, '-2 days');
        $rej->setRiskReason('Too many payments in a short period.');
        $t[] = $rej;
        $t[] = $this->makeTransaction($checking, $client, 'City Utilities', 'UA33 2211 0099', 12_080, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-1 day');

        // Carl's history.
        $this->makeTransaction($carlEur, $client2, 'Bookshop Online', 'UA44 5566 7788', 3_499, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-5 days');
        $this->makeTransaction($carlEur, $client2, 'Gym Membership', 'UA66 7788 9900', 4_500, Transaction::STATUS_COMPLETED, Transaction::RISK_OK, '-2 days');

        // --- Notifications ---------------------------------------------------------
        $this->makeNotification($client, 'Payment of 750.00 EUR to Landlord Properties was processed successfully.', '-12 days');
        $this->makeNotification($client, 'Payment of 11500.00 EUR to Auto Dealership was processed successfully.', '-3 days');
        $this->makeNotification($client, 'A payment to Unknown Recipient was blocked by fraud rules.', '-2 days');

        // Flush so users/transactions have IDs before we reference them in audit entries.
        $this->em->flush();

        // --- Audit history (so the admin audit page is populated on first load) -----
        $this->makeAudit(AuditLog::LOGIN_SUCCESS, $client, '-12 days');
        $this->makeAudit(AuditLog::PAYMENT_COMPLETED, $client, '-12 days', 'Transaction', (string) $t[0]->getId());
        $this->makeAudit(AuditLog::PAYMENT_MFA_REQUIRED, $client, '-3 days', 'Transaction', (string) $mfaTx->getId());
        $this->makeAudit(AuditLog::MFA_SUCCESS, $client, '-3 days', 'Transaction', (string) $mfaTx->getId());
        $this->makeAudit(AuditLog::PAYMENT_REJECTED, $client, '-2 days', 'Transaction', (string) $rej->getId(), ['reason' => 'risk_blocked']);
        $this->makeAudit(AuditLog::LOGIN_FAILED, null, '-1 day', null, null, ['reason' => 'invalid_credentials', 'email' => 'attacker@evil.test']);
        $this->makeAudit(AuditLog::LOGIN_SUCCESS, $admin, '-2 hours');

        $this->em->flush();

        $io->success('Demo data loaded.');
        $io->table(
            ['Email', 'Role', 'Status', 'Password'],
            [
                ['admin@example.com', 'ROLE_ADMIN', 'active', self::PASSWORD],
                ['employee@example.com', 'ROLE_EMPLOYEE', 'active', self::PASSWORD],
                ['client@example.com', 'ROLE_CLIENT', 'active', self::PASSWORD],
                ['client2@example.com', 'ROLE_CLIENT', 'active', self::PASSWORD],
                ['victor@example.com', 'ROLE_CLIENT', 'blocked', self::PASSWORD],
            ],
        );

        return Command::SUCCESS;
    }

    /** @param string[] $roles */
    private function makeUser(string $email, string $fullName, array $roles, string $status = User::STATUS_ACTIVE): User
    {
        $user = new User();
        $user->setEmail($email)->setFullName($fullName)->setRoles($roles)->setStatus($status);
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
        $this->em->persist($user);

        return $user;
    }

    private function makeAccount(User $user, string $number, string $currency, int $balanceCents): Account
    {
        $account = new Account();
        $account->setUser($user)->setAccountNumber($number)->setCurrency($currency)->setBalanceCents($balanceCents);
        $this->em->persist($account);

        return $account;
    }

    private function makeTransaction(
        Account $source,
        User $by,
        string $recipientName,
        string $recipientAccount,
        int $amountCents,
        string $status,
        string $riskStatus,
        string $when,
    ): Transaction {
        $createdAt = new \DateTimeImmutable($when);
        $t = new Transaction();
        $t->setSourceAccount($source)
            ->setCreatedBy($by)
            ->setRecipientName($recipientName)
            ->setRecipientAccount($recipientAccount)
            ->setAmountCents($amountCents)
            ->setCurrency($source->getCurrency())
            ->setStatus($status)
            ->setRiskStatus($riskStatus)
            ->setCreatedAt($createdAt);
        if (Transaction::STATUS_COMPLETED === $status) {
            $t->markConfirmed()->setCreatedAt($createdAt);
        }
        $this->em->persist($t);

        return $t;
    }

    private function makeNotification(User $user, string $message, string $when): void
    {
        $n = new Notification();
        $n->setUser($user)
            ->setType(Notification::TYPE_EMAIL)
            ->setRecipient($user->getEmail())
            ->setMessage($message)
            ->setStatus(Notification::STATUS_SENT)
            ->setCreatedAt(new \DateTimeImmutable($when));
        $this->em->persist($n);
    }

    /** @param array<string, mixed> $metadata */
    private function makeAudit(string $eventType, ?User $actor, string $when, ?string $entityType = null, ?string $entityId = null, array $metadata = []): void
    {
        $a = new AuditLog();
        $a->setEventType($eventType)
            ->setActorUserId($actor?->getId())
            ->setActorEmail($actor?->getEmail())
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setIpAddress('203.0.113.'.random_int(2, 250))
            ->setMetadata($metadata)
            ->setCreatedAt(new \DateTimeImmutable($when));
        $this->em->persist($a);
    }
}
