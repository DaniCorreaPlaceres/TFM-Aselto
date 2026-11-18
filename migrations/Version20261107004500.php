<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107004500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the client profile table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE client_profile (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                kind VARCHAR(32) NOT NULL,
                name VARCHAR(120) NOT NULL,
                firm_id CHAR(36) DEFAULT NULL,
                UNIQUE INDEX uniq_client_profile_user (user_id),
                INDEX idx_client_profile_firm (firm_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE client_profile');
    }
}
