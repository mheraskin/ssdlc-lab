<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption for TOTP seeds at rest.
 *
 * Uses libsodium's authenticated `crypto_secretbox` (XSalsa20-Poly1305). The 32-byte key
 * comes from `TOTP_ENCRYPTION_KEY` (base64) when present; otherwise it is derived from
 * `APP_SECRET` via HKDF-SHA256 with a fixed info string. In production a dedicated KMS
 * key is preferable; this is the lab default that keeps the secret out of plaintext
 * in the database.
 *
 * Output format: base64( nonce || ciphertext-with-tag ).
 */
class TotpSecretCipher
{
    /** HKDF info ("v1") — bump to rotate the derived key without changing APP_SECRET. */
    private const KDF_INFO = 'ssdlc-bank.totp-secret.v1';

    private readonly string $key;

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')]
        string $appSecret,
        #[Autowire('%env(TOTP_ENCRYPTION_KEY)%')]
        string $explicitKeyBase64 = '',
    ) {
        $key = '' !== $explicitKeyBase64
            ? sodium_base642bin($explicitKeyBase64, \SODIUM_BASE64_VARIANT_ORIGINAL)
            : hash_hkdf('sha256', $appSecret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::KDF_INFO);

        if (\strlen($key) !== \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('TOTP encryption key must be 32 bytes.');
        }
        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('TOTP secret ciphertext is malformed.');
        }
        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if (false === $plain) {
            throw new \RuntimeException('TOTP secret could not be decrypted (key mismatch or tampered ciphertext).');
        }

        return $plain;
    }
}
