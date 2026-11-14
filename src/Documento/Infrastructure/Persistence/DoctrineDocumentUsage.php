<?php

namespace App\Documento\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'document_usage')]
#[ORM\Index(name: 'idx_document_usage_owner_month', columns: ['owner_id', 'month'])]
class DoctrineDocumentUsage
{
    #[ORM\Id]
    #[ORM\Column(length: 36, options: ['fixed' => true])]
    public string $documentId;

    #[ORM\Column(length: 36, options: ['fixed' => true])]
    public string $ownerId;

    #[ORM\Column(length: 7)]
    public string $month;
}
