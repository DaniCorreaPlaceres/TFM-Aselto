<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107011000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record whether an account chose a password, and store access links.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD activated TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE identity_user SET activated = 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE access_token (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                purpose VARCHAR(16) NOT NULL,
                expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_access_token_hash (token_hash),
                INDEX idx_access_token_user (user_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE access_token');
        $this->addSql('ALTER TABLE identity_user DROP activated');
    }
}
