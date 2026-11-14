<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store platform registration invitations without creating accounts before acceptance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_invitation (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, target VARCHAR(16) NOT NULL, token_hash VARCHAR(64) NOT NULL, sent_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_platform_invitation_email (email), UNIQUE INDEX uniq_platform_invitation_token (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM platform_invitation'), 'Invitation records must not be discarded.');
        $this->addSql('DROP TABLE platform_invitation');
    }
}
