<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve scoped quarterly snapshots and immutable close/reopen history without modifying documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE financial_quarter (owner_id VARCHAR(36) NOT NULL, scope VARCHAR(36) NOT NULL, year SMALLINT NOT NULL, number SMALLINT NOT NULL, closed TINYINT NOT NULL, snapshot JSON NOT NULL, history JSON NOT NULL, version INT DEFAULT 1 NOT NULL, PRIMARY KEY (owner_id, scope, year, number)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM financial_quarter'), 'Recorded financial history cannot be discarded.');
        $this->addSql('DROP TABLE financial_quarter');
    }
}
