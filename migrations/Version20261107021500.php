<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107021500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the documents a client adds and reviews.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_document (
                id CHAR(36) NOT NULL,
                client_user_id CHAR(36) NOT NULL,
                firm_id CHAR(36) DEFAULT NULL,
                original_name VARCHAR(180) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                media_type VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL,
                direction VARCHAR(16) DEFAULT NULL,
                issued_on DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
                party_name VARCHAR(120) DEFAULT NULL,
                tax_id VARCHAR(20) DEFAULT NULL,
                base_cents INT DEFAULT NULL,
                vat_rate INT DEFAULT NULL,
                quota_cents INT DEFAULT NULL,
                total_cents INT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_client_document_owner (client_user_id),
                INDEX idx_client_document_firm (firm_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_document');
    }
}
