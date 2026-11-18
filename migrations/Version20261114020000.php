<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Protect firm edits and administrative email changes with revocable authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD auth_version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE advisory_firm ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TABLE admin_email_change (user_id VARCHAR(36) NOT NULL, source_email VARCHAR(180) NOT NULL, new_email VARCHAR(180) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL, auth_version INT NOT NULL, confirmed_at BIGINT DEFAULT NULL, notice_sent TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_admin_email_change_token (token_hash), PRIMARY KEY (user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing authentication revisions could restore invalidated access.');
    }
}
