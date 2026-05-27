<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\ApiTestCase;

class AdminApiTest extends ApiTestCase
{
    /**
     * Security (RBAC): only ROLE_ADMIN may reach the admin API.
     */
    public function testClientIsForbiddenFromAdmin(): void
    {
        $client = $this->makeUser('client@example.com', ['ROLE_CLIENT']);
        self::assertSame(403, $this->api('GET', '/api/admin/users', null, $client)->getStatusCode());
    }

    public function testEmployeeIsForbiddenFromAdmin(): void
    {
        $employee = $this->makeUser('employee@example.com', ['ROLE_EMPLOYEE']);
        self::assertSame(403, $this->api('GET', '/api/admin/users', null, $employee)->getStatusCode());
    }

    public function testAdminListsUsersWithoutPasswordHashes(): void
    {
        $admin = $this->makeUser('admin@example.com', ['ROLE_ADMIN']);
        $this->makeUser('client@example.com', ['ROLE_CLIENT']);

        $res = $this->api('GET', '/api/admin/users', null, $admin);
        self::assertSame(200, $res->getStatusCode());

        $body = $this->json($res);
        self::assertGreaterThanOrEqual(2, \count($body['users']));
        foreach ($body['users'] as $user) {
            self::assertArrayNotHasKey('password', $user);
        }
        self::assertStringNotContainsString('$2y$', (string) $res->getContent());
    }

    public function testAdminCanViewAuditLogs(): void
    {
        $admin = $this->makeUser('admin@example.com', ['ROLE_ADMIN']);
        $res = $this->api('GET', '/api/admin/audit-logs', null, $admin);
        self::assertSame(200, $res->getStatusCode());
        self::assertArrayHasKey('auditLogs', $this->json($res));
    }

    public function testAdminCanBlockAndUnblockAUser(): void
    {
        $admin = $this->makeUser('admin@example.com', ['ROLE_ADMIN']);
        $target = $this->makeUser('client@example.com', ['ROLE_CLIENT']);

        $blocked = $this->api('POST', "/api/admin/users/{$target->getId()}/block", null, $admin);
        self::assertSame(200, $blocked->getStatusCode());
        self::assertSame(User::STATUS_BLOCKED, $this->json($blocked)['user']['status']);

        $unblocked = $this->api('POST', "/api/admin/users/{$target->getId()}/unblock", null, $admin);
        self::assertSame(200, $unblocked->getStatusCode());
        self::assertSame(User::STATUS_ACTIVE, $this->json($unblocked)['user']['status']);
    }
}
