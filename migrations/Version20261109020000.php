<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store client conversations, general requests, attachments and per-user reading progress.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client_conversation (id VARCHAR(36) NOT NULL, owner_id VARCHAR(36) NOT NULL, firm_id VARCHAR(36) NOT NULL, subject VARCHAR(120) NOT NULL, request TINYINT NOT NULL, document_id VARCHAR(36) DEFAULT NULL, messages JSON NOT NULL, readers JSON NOT NULL, resolved_by VARCHAR(36) DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, version INT DEFAULT 1 NOT NULL, INDEX conversation_owner (owner_id), INDEX conversation_firm (firm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM client_conversation'), 'Conversation history cannot be discarded.');
        $this->addSql('DROP TABLE client_conversation');
    }
}
