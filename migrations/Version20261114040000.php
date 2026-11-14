<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Manual licenses and persistent monthly document usage, including existing records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account_license (subject_id CHAR(36) NOT NULL, kind VARCHAR(8) NOT NULL, plan VARCHAR(16) NOT NULL, history JSON NOT NULL, PRIMARY KEY (subject_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document_usage (document_id CHAR(36) NOT NULL, owner_id CHAR(36) NOT NULL, month VARCHAR(7) NOT NULL, INDEX idx_document_usage_owner_month (owner_id, month), PRIMARY KEY (document_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        // UTC is the persisted SystemClock convention. No MySQL timezone tables required.
        foreach ($this->connection->iterateAssociative('SELECT id, client_user_id, created_at FROM client_document') as $row) {
            $month = new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC'));
            $this->addSql('INSERT INTO document_usage (document_id, owner_id, month) VALUES (?, ?, ?)', [$row['id'], $row['client_user_id'], $month->setTimezone(new \DateTimeZone('Atlantic/Canary'))->format('Y-m')]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing usage would restore consumed quota and discard license history.');
    }
}
