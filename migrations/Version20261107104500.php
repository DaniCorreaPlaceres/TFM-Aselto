<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember whether a document proposal was read or is an example.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document ADD proposal VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document DROP proposal');
    }
}
