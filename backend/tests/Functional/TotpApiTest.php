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
 *
 * The plaintext seed is the value returned ONCE by /api/totp/setup. The DB column stores
 * only the ciphertext (encrypted at rest), so the tests never read the secret back from
 * `users.totp_secret`.
 */
class TotpApiTest extends ApiTestCase
{
    public function testEnrollEnableAndUseForRiskyPayment(): void
    {
        $user = $this->makeUser('client@example.com');
        $account = $this->makeAccount($user, 'UA13 0000 1111', 2_000_000); // €20,000.00

        // 1. Setup returns a fresh secret + otpauth:// URI; enrollment NOT yet confirmed.
        $setup = $this->json($this->api('POST', '/api/totp/setup', null, $user));
        $plainSecret = $setup['secret'];
        self::assertNotEmpty($plainSecret);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioningUri']);
        self::assertStringContainsString('issuer=SSDLC%20Bank', $setup['provisioningUri']);
        self::assertFalse($setup['enabled']);
        self::assertFalse($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);

        // The DB column must hold the CIPHERTEXT, never the plaintext seed.
        $stored = (string) $this->em()->getConnection()->fetchOne('SELECT totp_secret FROM users WHERE id = ?', [$user->getId()]);
        self::assertNotSame($plainSecret, $stored);
        self::assertStringNotContainsString($plainSecret, $stored);

        // 2. Confirm enrollment with a code computed from that seed.
        $enable = $this->api('POST', '/api/totp/enable', [
            'code' => TOTP::createFromSecret($plainSecret)->now(),
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
        $ok = $this->api('POST', "/api/payments/{$txId}/confirm", [
            'code' => TOTP::createFromSecret($plainSecret)->now(),
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
        $plainSecret = $setup['secret'];
        $this->api('POST', '/api/totp/enable', ['code' => TOTP::createFromSecret($plainSecret)->now()], $user);

        // Wrong code can NOT disable MFA.
        $bad = $this->api('POST', '/api/totp/disable', ['code' => '000000'], $user);
        self::assertSame(422, $bad->getStatusCode());
        self::assertTrue($this->json($this->api('GET', '/api/me', null, $user))['user']['totpEnabled']);

        // A current code from the authenticator does.
        $ok = $this->api('POST', '/api/totp/disable', [
            'code' => TOTP::createFromSecret($plainSecret)->now(),
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

    /**
     * Replay protection: once a TOTP code has been accepted to confirm one payment, the
     * SAME code must not also confirm a second payment — even if it is still inside its
     * 30-second validity window.
     */
    public function testTotpCodeCannotBeReplayedAcrossPayments(): void
    {
        $user = $this->makeUser('client@example.com');
        $account = $this->makeAccount($user, 'UA13 0000 2222', 5_000_000); // €50,000.00
        $setup = $this->json($this->api('POST', '/api/totp/setup', null, $user));
        $plainSecret = $setup['secret'];
        $this->api('POST', '/api/totp/enable', ['code' => TOTP::createFromSecret($plainSecret)->now()], $user);

        $tx1 = $this->json($this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(), 'recipientName' => 'A',
            'recipientAccount' => 'UA00 0001', 'amount' => 11_000,
        ], $user))['transaction']['id'];
        $tx2 = $this->json($this->api('POST', '/api/payments', [
            'sourceAccountId' => $account->getId(), 'recipientName' => 'B',
            'recipientAccount' => 'UA00 0002', 'amount' => 11_000,
        ], $user))['transaction']['id'];

        $code = TOTP::createFromSecret($plainSecret)->now();

        // First confirmation: accepted, counter advances.
        self::assertSame(201, $this->api('POST', "/api/payments/{$tx1}/confirm", ['code' => $code], $user)->getStatusCode());

        // Same code submitted for the second payment within the same step → REJECTED.
        self::assertSame(422, $this->api('POST', "/api/payments/{$tx2}/confirm", ['code' => $code], $user)->getStatusCode());

        // Only one debit happened.
        $accounts = $this->json($this->api('GET', '/api/accounts', null, $user))['accounts'];
        self::assertSame('39000.00', $accounts[0]['balance']); // 50,000 - 11,000
    }
}
