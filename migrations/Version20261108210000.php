<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store optional income withholding percentage and quota while preserving existing amounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document ADD withholding_rate INT DEFAULT NULL, ADD withholding_quota_cents INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM client_document WHERE withholding_rate IS NOT NULL'), 'Removing withholding columns would lose recorded data.');
        $this->addSql('ALTER TABLE client_document DROP withholding_rate, DROP withholding_quota_cents');
    }
}
