<?php

namespace App\Security;

use App\Entity\Account;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Enforces the RBAC rule that a client may only see and use their OWN accounts.
 * Employees/admins may view any account (read-only) but the "use" (pay-from) right
 * is restricted to the owner.
 */
class AccountVoter extends Voter
{
    public const VIEW = 'ACCOUNT_VIEW';
    public const USE = 'ACCOUNT_USE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::USE], true) && $subject instanceof Account;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Account $subject */
        $isOwner = $subject->getUser()->getId() === $user->getId();

        return match ($attribute) {
            self::USE => $isOwner,
            self::VIEW => $isOwner || \in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_EMPLOYEE', $user->getRoles(), true),
            default => false,
        };
    }
}
