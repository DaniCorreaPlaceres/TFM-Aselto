<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Version client profiles to protect concurrent administrative and fiscal edits.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_profile ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing profile versions would remove concurrent edit protection.');
    }
}
