<?php

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Tests\ApiTestCase;

class SecurityApiTest extends ApiTestCase
{
    public function testSecurityHeadersArePresent(): void
    {
        $res = $this->api('GET', '/api/health');

        self::assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $res->headers->get('X-Frame-Options'));
        self::assertStringContainsString("default-src 'none'", (string) $res->headers->get('Content-Security-Policy'));
    }

    /**
     * Errors under /api are JSON, never an HTML error page (and don't leak internals).
     */
    public function testUnknownApiRouteReturnsJsonError(): void
    {
        $user = $this->makeUser('client@example.com');
        $res = $this->api('GET', '/api/this-route-does-not-exist', null, $user);

        self::assertSame(404, $res->getStatusCode());
        self::assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
        self::assertArrayHasKey('error', $this->json($res));
    }

    public function testProtectedEndpointWithoutTokenIsUnauthorized(): void
    {
        self::assertSame(401, $this->api('GET', '/api/accounts')->getStatusCode());
    }

    /**
     * Security: the audit log is append-only — the database trigger blocks UPDATE and
     * DELETE even via raw SQL, so history cannot be tampered with.
     */
    public function testAuditLogIsAppendOnlyAtTheDatabaseLevel(): void
    {
        $em = $this->em();
        $em->persist((new AuditLog())->setEventType(AuditLog::LOGIN_SUCCESS));
        $em->flush();

        // DBAL 4 always uses savepoints for nested transactions, so the trigger's exception
        // is contained to the inner savepoint and the outer test transaction survives.
        $conn = $em->getConnection();

        self::assertTrue($this->statementIsBlocked($conn, "UPDATE audit_logs SET event_type = 'tampered'"), 'UPDATE must be blocked');
        self::assertTrue($this->statementIsBlocked($conn, 'DELETE FROM audit_logs'), 'DELETE must be blocked');
    }

    private function statementIsBlocked(\Doctrine\DBAL\Connection $conn, string $sql): bool
    {
        // Run inside a savepoint so the trigger's exception doesn't poison the outer
        // (test-wrapping) transaction.
        $conn->beginTransaction();
        try {
            $conn->executeStatement($sql);
            $conn->commit();

            return false; // not blocked — should not happen
        } catch (\Throwable $e) {
            $conn->rollBack();

            return str_contains($e->getMessage(), 'append-only');
        }
    }
}
