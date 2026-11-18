<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the document territory tax and rates in hundredths of a percent, preserving existing VAT documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE client_document ADD tax VARCHAR(4) DEFAULT 'iva' NOT NULL, CHANGE vat_rate tax_rate INT DEFAULT NULL");
        $this->addSql('UPDATE client_document SET tax_rate = tax_rate * 100 WHERE tax_rate IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne("SELECT COUNT(*) FROM client_document WHERE tax <> 'iva' OR (tax_rate IS NOT NULL AND tax_rate NOT IN (0, 400, 1000, 2100))"), 'Documents with territory taxes cannot be converted back to VAT without losing information.');
        $this->addSql('UPDATE client_document SET tax_rate = tax_rate / 100 WHERE tax_rate IS NOT NULL');
        $this->addSql('ALTER TABLE client_document DROP tax, CHANGE tax_rate vat_rate INT DEFAULT NULL');
    }
}
