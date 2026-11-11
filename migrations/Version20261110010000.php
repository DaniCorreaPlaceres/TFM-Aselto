<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist coalesced financial update notifications in the business transaction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quarter_update_outbox (owner_id VARCHAR(36) NOT NULL, scope VARCHAR(36) NOT NULL, revision BIGINT DEFAULT 1 NOT NULL, published_revision BIGINT DEFAULT 0 NOT NULL, PRIMARY KEY (owner_id, scope)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM quarter_update_outbox WHERE revision > published_revision'), 'Unpublished notifications cannot be discarded.');
        $this->addSql('DROP TABLE quarter_update_outbox');
    }
}
