<?php

namespace App\Finanzas\Infrastructure\Realtime;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

// The outbox uses atomic DBAL statements, but must remain part of schema comparisons.
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class QuarterUpdateSchema
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $table = $event->getSchema()->createTable('quarter_update_outbox');
        $table->addColumn('owner_id', 'string', ['length' => 36]);
        $table->addColumn('scope', 'string', ['length' => 36]);
        $table->addColumn('revision', 'bigint', ['default' => 1]);
        $table->addColumn('published_revision', 'bigint', ['default' => 0]);
        $table->setPrimaryKey(['owner_id', 'scope']);
    }
}
