<?php

namespace App\Tests\Security;

use App\Entity\Account;
use App\Entity\User;
use App\Security\AccountVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(AccountVoter::class)]
class AccountVoterTest extends TestCase
{
    private function user(int $id, array $roles = ['ROLE_CLIENT']): User
    {
        $u = (new User())->setEmail("u{$id}@example.com")->setRoles($roles);
        // Doctrine assigns the id in real life; set it directly for the unit test.
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($u, $id);

        return $u;
    }

    private function account(User $owner): Account
    {
        return (new Account())->setUser($owner)->setAccountNumber('UA00 0000');
    }

    private function vote(User $voterUser, Account $account, string $attribute): int
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($voterUser);

        return (new AccountVoter())->vote($token, $account, [$attribute]);
    }

    public function testOwnerMayUseTheirAccount(): void
    {
        $owner = $this->user(1);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $this->account($owner), AccountVoter::USE));
    }

    public function testNonOwnerMayNotUseAccount(): void
    {
        $owner = $this->user(1);
        $intruder = $this->user(2);
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($intruder, $this->account($owner), AccountVoter::USE));
    }

    public function testAdminMayViewAnyAccountButClientMayNot(): void
    {
        $owner = $this->user(1);
        $admin = $this->user(2, ['ROLE_ADMIN']);
        $otherClient = $this->user(3, ['ROLE_CLIENT']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $this->account($owner), AccountVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($otherClient, $this->account($owner), AccountVoter::VIEW));
    }

    public function testAdminStillMayNotUseAnotherUsersAccount(): void
    {
        $owner = $this->user(1);
        $admin = $this->user(2, ['ROLE_ADMIN']);
        // VIEW is allowed for admins, but USE (pay-from) is owner-only.
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($admin, $this->account($owner), AccountVoter::USE));
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $owner = $this->user(1);
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($owner, $this->account($owner), 'SOMETHING_ELSE'));
    }
}
