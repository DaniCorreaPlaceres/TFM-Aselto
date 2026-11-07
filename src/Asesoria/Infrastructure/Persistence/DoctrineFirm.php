<?php

namespace App\Asesoria\Infrastructure\Persistence;

use App\Asesoria\Domain\Firm;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advisory_firm')]
class DoctrineFirm
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 120)]
    private string $name;

    public function apply(Firm $firm): void
    {
        $this->id = $firm->id();
        $this->name = $firm->name();
    }

    public function toDomain(): Firm
    {
        return new Firm($this->id, $this->name);
    }
}
