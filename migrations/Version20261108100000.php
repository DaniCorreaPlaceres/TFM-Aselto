<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store optional equivalence surcharge rate and quota without changing existing document amounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document ADD surcharge INT DEFAULT NULL, ADD surcharge_quota_cents INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM client_document WHERE surcharge IS NOT NULL'), 'Removing surcharge columns would lose recorded amounts.');
        $this->addSql('ALTER TABLE client_document DROP surcharge, DROP surcharge_quota_cents');
    }
}
