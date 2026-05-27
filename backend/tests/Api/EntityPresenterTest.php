<?php

namespace App\Tests\Api;

use App\Api\EntityPresenter;
use App\Entity\Account;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityPresenter::class)]
class EntityPresenterTest extends TestCase
{
    /**
     * Security: the serialised user must never expose the password hash.
     */
    public function testUserArrayNeverExposesPasswordHash(): void
    {
        $hash = '$2y$10$abcdefghijklmnopqrstuvSECRETHASHvalue123456789';
        $user = (new User())
            ->setEmail('client@example.com')
            ->setFullName('Clara Client')
            ->setRoles(['ROLE_CLIENT']);
        $user->setPassword($hash);

        $array = EntityPresenter::user($user);

        self::assertArrayNotHasKey('password', $array);
        self::assertArrayNotHasKey('passwordHash', $array);
        self::assertStringNotContainsString($hash, json_encode($array));
        self::assertSame('client@example.com', $array['email']);
        self::assertContains('ROLE_CLIENT', $array['roles']);
    }

    public function testAccountArrayFormatsBalanceAndOmitsInternals(): void
    {
        $user = (new User())->setEmail('client@example.com')->setFullName('Clara');
        $account = (new Account())
            ->setUser($user)
            ->setAccountNumber('UA13 0000 1111 2222')
            ->setCurrency('EUR')
            ->setBalanceCents(524_318);

        $array = EntityPresenter::account($account);

        self::assertSame(524318, $array['balanceCents']);
        self::assertSame('5243.18', $array['balance']);
        self::assertSame('EUR', $array['currency']);
        self::assertSame('client@example.com', $array['ownerEmail']);
    }
}
