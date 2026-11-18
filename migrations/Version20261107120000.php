<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the fiscal contact of a client and an advisory firm.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE client_profile
                ADD tax_id VARCHAR(16) DEFAULT '' NOT NULL,
                ADD phone VARCHAR(16) DEFAULT '' NOT NULL,
                ADD street VARCHAR(120) DEFAULT '' NOT NULL,
                ADD postal_code VARCHAR(5) DEFAULT '' NOT NULL,
                ADD municipality VARCHAR(80) DEFAULT '' NOT NULL,
                ADD province VARCHAR(40) DEFAULT '' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE advisory_firm
                ADD tax_id VARCHAR(16) DEFAULT '' NOT NULL,
                ADD phone VARCHAR(16) DEFAULT '' NOT NULL,
                ADD street VARCHAR(120) DEFAULT '' NOT NULL,
                ADD postal_code VARCHAR(5) DEFAULT '' NOT NULL,
                ADD municipality VARCHAR(80) DEFAULT '' NOT NULL,
                ADD province VARCHAR(40) DEFAULT '' NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_profile DROP tax_id, DROP phone, DROP street, DROP postal_code, DROP municipality, DROP province');
        $this->addSql('ALTER TABLE advisory_firm DROP tax_id, DROP phone, DROP street, DROP postal_code, DROP municipality, DROP province');
    }
}
