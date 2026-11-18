<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distinguish explicitly reviewed indirect tax exemptions from zero tax rates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document ADD tax_exempt TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dropping exemption flags would discard reviewed fiscal information.');
    }
}
