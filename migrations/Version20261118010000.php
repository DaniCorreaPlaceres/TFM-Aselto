<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add consent-based invitations linking existing independent clients to firms.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE firm_link_invitation (id VARCHAR(36) NOT NULL, owner_id VARCHAR(36) NOT NULL, firm_id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, token_hash VARCHAR(64) NOT NULL, sent_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, sent_by VARCHAR(36) NOT NULL, accepted_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_firm_link_pair (owner_id, firm_id), UNIQUE INDEX uniq_firm_link_token (token_hash), INDEX idx_firm_link_firm (firm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Link consent history must not be discarded.');
    }
}
