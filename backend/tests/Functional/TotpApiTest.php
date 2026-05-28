<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use OTPHP\TOTP;

/**
 * End-to-end test of the TOTP MFA flow over the real API:
 *   enroll -> confirm -> high-value payment -> confirm with authenticator code -> debit.
 *
 * The "authenticator app" is replaced in tests by computing the current TOTP code from
 * the same secret on the server side — exactly how Google Authenticator / 1Password /
 * Authy compute it on the user's device. No app, no human, no waiting.
 */
class TotpApiTest extends ApiTestCase
{
    public function testEnrollEnableAndUseForRiskyPayment(): void
    {
        $user = $this->makeUser('client@example.com');
        $account = $this->makeAccount($user, 'UA13 0000 1111', 2_000_000); // €20,000.00

        // 1. Setup returns a fresh secret + otpauth:// URI; enrollment NOT yet confirmed.
        $setup = $this->json($this->api('POST', '/api/totp/setup', null, $user));
        self::assertNotEmpty($setup['secret']);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioningUri']);
        self::assertStringContainsString('issuer=SSDLC%20Bank', $setup['provisioningUri']);
        self::assertFalse($setup['enabled']);
        // /api/me must not flip until the first code is confirmed.
        self::assertFalse($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);

        // 2. Confirm enrollment with a code computed from that secret (= same code the
        //    authenticator app would show).
        $enable = $this->api('POST', '/api/totp/enable', [
            'code' => TOTP::createFromSecret($setup['secret'])->now(),
        ], $user);
        self::assertSame(200, $enable->getStatusCode());
        self::assertTrue($this->json($enable)['enabled']);
        self::assertTrue($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);

        // 3. A high-value payment now asks for an authenticator code (no email is sent).
        $create = $this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(),
            'recipientName' => 'Auto Dealer',
            'recipientAccount' => 'UA77 6655',
            'amount' => 11_000,
        ], $user);
        self::assertSame(202, $create->getStatusCode());
        $body = $this->json($create);
        self::assertSame('mfa_required', $body['status']);
        self::assertSame('pending_mfa', $body['transaction']['status']);
        self::assertStringContainsString('authenticator', $body['message']);
        $txId = $body['transaction']['id'];

        // 4. A wrong code is rejected.
        $bad = $this->api('POST', "/api/payments/{$txId}/confirm", ['code' => '000000'], $user);
        self::assertSame(422, $bad->getStatusCode());

        // 5. The current TOTP code completes the payment (true MFA: password + possession).
        // The User instance is detached after the KernelBrowser request scope, so read the
        // freshly-stored secret directly from the DB rather than refreshing the stale object.
        $secret = (string) $this->em()->getConnection()->fetchOne('SELECT totp_secret FROM users WHERE id = ?', [$user->getId()]);
        $user->setTotpSecret($secret);
        $ok = $this->api('POST', "/api/payments/{$txId}/confirm", [
            'code' => TOTP::createFromSecret($user->getTotpSecret())->now(),
        ], $user);
        self::assertSame(201, $ok->getStatusCode());
        self::assertSame('completed', $this->json($ok)['status']);

        // 6. Balance debited — server-side, never client-controlled.
        $accounts = $this->json($this->api('GET', '/api/accounts', null, $user))['accounts'];
        self::assertSame('9000.00', $accounts[0]['balance']); // 20,000 - 11,000

        // 7. The audit log records the factor that completed the second step.
        $row = $this->em()->getConnection()->fetchAssociative(
            "SELECT metadata FROM audit_logs WHERE event_type = 'mfa_success' AND entity_id = ? ORDER BY id DESC LIMIT 1",
            [(string) $txId]
        );
        self::assertIsArray($row);
        /** @var array{factor?: string} $meta */
        $meta = json_decode((string) $row['metadata'], true);
        self::assertSame('totp', $meta['factor'] ?? null);
    }

    public function testDisableRequiresFreshAuthenticatorCode(): void
    {
        $user = $this->makeUser('client@example.com');
        $setup = $this->json($this->api('POST', '/api/totp/setup', null, $user));
        $this->api('POST', '/api/totp/enable', ['code' => TOTP::createFromSecret($setup['secret'])->now()], $user);

        // Wrong code can NOT disable MFA.
        $bad = $this->api('POST', '/api/totp/disable', ['code' => '000000'], $user);
        self::assertSame(422, $bad->getStatusCode());
        self::assertTrue($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);

        // A current code from the authenticator does.
        // The User instance is detached after the KernelBrowser request scope, so read the
        // freshly-stored secret directly from the DB rather than refreshing the stale object.
        $secret = (string) $this->em()->getConnection()->fetchOne('SELECT totp_secret FROM users WHERE id = ?', [$user->getId()]);
        $user->setTotpSecret($secret);
        $ok = $this->api('POST', '/api/totp/disable', [
            'code' => TOTP::createFromSecret($user->getTotpSecret())->now(),
        ], $user);
        self::assertSame(200, $ok->getStatusCode());
        self::assertFalse($this->json($ok)['enabled']);
        self::assertFalse($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);
    }

    public function testEnableWithWrongCodeKeepsUserUnenrolled(): void
    {
        $user = $this->makeUser('client@example.com');
        $this->api('POST', '/api/totp/setup', null, $user);

        $bad = $this->api('POST', '/api/totp/enable', ['code' => '000000'], $user);
        self::assertSame(422, $bad->getStatusCode());
        self::assertFalse($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);
    }

    public function testSetupRequiresAuthentication(): void
    {
        self::assertSame(401, $this->api('POST', '/api/totp/setup')->getStatusCode());
    }
}
