<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hardening of the TOTP factor:
 *   1. users.totp_last_used_counter — track the highest TOTP step counter ever accepted
 *      so a code cannot be replayed within its validity window.
 *   2. Any previously-stored TOTP secret was plaintext; storage format is now ciphertext
 *      (encrypted at rest by TotpSecretCipher), so existing enrollments must be reset.
 */
final class Version20260528100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TOTP replay counter + reset existing plaintext secrets (format is now encrypted at rest)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN totp_last_used_counter BIGINT DEFAULT NULL');

        // The storage format for users.totp_secret is changing from plaintext base32 to
        // base64(nonce + libsodium-secretbox ciphertext). Drop any pre-existing rows so
        // the application never tries to decrypt a value that was never encrypted.
        $this->addSql('UPDATE users SET totp_secret = NULL, totp_enabled = FALSE WHERE totp_secret IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN totp_last_used_counter');
    }
}
