<?php

namespace App\Tests;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for functional API tests. Each test runs inside a database transaction that
 * is rolled back afterwards (dama/doctrine-test-bundle), so tests are isolated and the
 * demo seed data is irrelevant.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'Password123!';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @param string[] $roles */
    protected function makeUser(
        string $email,
        array $roles = ['ROLE_CLIENT'],
        string $status = User::STATUS_ACTIVE,
        string $password = self::PASSWORD,
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new User())->setEmail($email)->setFullName('Test '.$email)->setRoles($roles)->setStatus($status);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em = $this->em();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function makeAccount(User $user, string $number, int $balanceCents = 100_000, string $currency = 'EUR'): Account
    {
        $account = (new Account())->setUser($user)->setAccountNumber($number)->setCurrency($currency)->setBalanceCents($balanceCents);
        $em = $this->em();
        $em->persist($account);
        $em->flush();

        return $account;
    }

    protected function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    /**
     * Make a JSON API request. If $as is given, a Bearer token is attached for that user.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, mixed>      $server extra server params (e.g. REMOTE_ADDR)
     */
    protected function api(string $method, string $uri, ?array $json = null, ?User $as = null, array $server = []): Response
    {
        $server['CONTENT_TYPE'] = 'application/json';
        $server['HTTP_ACCEPT'] = 'application/json';
        if (null !== $as) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($as);
        }

        $this->client->request($method, $uri, server: $server, content: null !== $json ? (string) json_encode($json) : null);

        return $this->client->getResponse();
    }

    /** @return array<string, mixed> */
    protected function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true) ?? [];
    }
}
