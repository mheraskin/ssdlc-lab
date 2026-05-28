<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * TOTP-based MFA (RFC 6238) — adds a possession factor on top of the email-OTP step-up.
 *
 * - users.totp_secret      : Base32 TOTP seed, nullable; only populated after enrollment starts
 * - users.totp_enabled     : true once the user confirms the first code from their authenticator
 * - mfa_challenges.factor  : 'totp' | 'email_otp' — which factor the challenge expects at confirm
 * - mfa_challenges.code_hash made NULL-able (TOTP challenges have no stored code; the code is
 *   computed from secret+time on each verification)
 */
final class Version20260528000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TOTP MFA: users.totp_secret/totp_enabled, mfa_challenges.factor, code_hash nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN totp_enabled BOOLEAN NOT NULL DEFAULT FALSE');

        $this->addSql("ALTER TABLE mfa_challenges ADD COLUMN factor VARCHAR(20) NOT NULL DEFAULT 'email_otp'");
        $this->addSql('ALTER TABLE mfa_challenges ALTER COLUMN code_hash DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mfa_challenges ALTER COLUMN code_hash SET NOT NULL');
        $this->addSql('ALTER TABLE mfa_challenges DROP COLUMN factor');
        $this->addSql('ALTER TABLE users DROP COLUMN totp_enabled');
        $this->addSql('ALTER TABLE users DROP COLUMN totp_secret');
    }
}
