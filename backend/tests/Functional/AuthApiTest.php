<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\ApiTestCase;

class AuthApiTest extends ApiTestCase
{
    /** Each test logs in from its own IP so the per-IP login rate limiter never bleeds across tests. */
    private function login(string $email, string $password, string $ip = '10.0.0.1'): array
    {
        $res = $this->api('POST', '/api/login', ['email' => $email, 'password' => $password], null, ['REMOTE_ADDR' => $ip]);

        return [$res->getStatusCode(), $this->json($res), (string) $res->getContent()];
    }

    public function testLoginSucceedsAndNeverLeaksPasswordHash(): void
    {
        $this->makeUser('client@example.com');

        [$status, $body, $raw] = $this->login('client@example.com', self::PASSWORD, '10.1.0.1');

        self::assertSame(200, $status);
        self::assertNotEmpty($body['token']);
        self::assertSame('client@example.com', $body['user']['email']);
        self::assertArrayNotHasKey('password', $body['user']);
        self::assertStringNotContainsString('$2y$', $raw, 'response must not contain a bcrypt hash');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->makeUser('client@example.com');
        [$status] = $this->login('client@example.com', 'wrong-password', '10.1.0.2');
        self::assertSame(401, $status);
    }

    public function testUnknownEmailIsRejected(): void
    {
        [$status] = $this->login('nobody@example.com', self::PASSWORD, '10.1.0.3');
        self::assertSame(401, $status);
    }

    public function testBlockedUserCannotLogIn(): void
    {
        $this->makeUser('victor@example.com', ['ROLE_CLIENT'], User::STATUS_BLOCKED);
        [$status] = $this->login('victor@example.com', self::PASSWORD, '10.1.0.4');
        self::assertSame(403, $status);
    }

    public function testLoginRequiresEmailAndPassword(): void
    {
        $res = $this->api('POST', '/api/login', ['email' => ''], null, ['REMOTE_ADDR' => '10.1.0.5']);
        self::assertSame(400, $res->getStatusCode());
    }

    public function testMeRequiresAuthentication(): void
    {
        $res = $this->api('GET', '/api/me');
        self::assertSame(401, $res->getStatusCode());
    }

    public function testMeReturnsTheAuthenticatedUser(): void
    {
        $user = $this->makeUser('client@example.com');
        $res = $this->api('GET', '/api/me', null, $user);
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('client@example.com', $this->json($res)['user']['email']);
    }

    public function testLogoutReturnsNoContent(): void
    {
        $user = $this->makeUser('client@example.com');
        $res = $this->api('POST', '/api/logout', null, $user);
        self::assertSame(204, $res->getStatusCode());
    }

    /**
     * Security: brute-force protection. The 6th attempt within the window is throttled.
     */
    public function testLoginIsRateLimited(): void
    {
        $this->makeUser('client@example.com');
        // Fresh, unique IP so this test owns the whole rate-limit bucket.
        $ip = '172.16.'.random_int(0, 255).'.'.random_int(1, 254);

        $codes = [];
        for ($i = 0; $i < 6; ++$i) {
            [$status] = $this->login('client@example.com', 'wrong-password', $ip);
            $codes[] = $status;
        }

        self::assertSame(401, $codes[0], 'first attempts are normal auth failures');
        self::assertSame(429, $codes[5], 'the 6th attempt is rate limited');
    }
}
