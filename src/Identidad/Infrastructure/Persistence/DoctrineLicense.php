<?php

namespace App\Identidad\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account_license')]
class DoctrineLicense
{
    #[ORM\Id]
    #[ORM\Column(length: 36, options: ['fixed' => true])]
    public string $subjectId;

    #[ORM\Column(length: 8)]
    public string $kind;

    #[ORM\Column(length: 16)]
    public string $plan;

    /** @var list<array<string, string>> */
    #[ORM\Column(type: 'json')]
    public array $history;
}
