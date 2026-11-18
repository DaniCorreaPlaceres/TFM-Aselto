<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve review history, supporting files and pending requests with optimistic document versions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_document ADD review_history JSON DEFAULT NULL, ADD supporting_files JSON DEFAULT NULL, ADD pending_request VARCHAR(1000) DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM client_document WHERE (review_history IS NOT NULL AND JSON_LENGTH(review_history) > 0) OR (supporting_files IS NOT NULL AND JSON_LENGTH(supporting_files) > 0) OR pending_request IS NOT NULL'), 'Review information must not be removed.');
        $this->addSql('ALTER TABLE client_document DROP review_history, DROP supporting_files, DROP pending_request, DROP version');
    }
}
