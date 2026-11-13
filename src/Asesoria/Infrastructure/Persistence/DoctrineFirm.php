<?php

namespace App\Asesoria\Infrastructure\Persistence;

use App\Asesoria\Domain\Firm;
use App\Shared\Domain\FiscalContact;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advisory_firm')]
class DoctrineFirm
{
    #[ORM\Id]
    #[ORM\Column(length: 36, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 16, options: ['default' => ''])]
    private string $taxId = '';

    #[ORM\Column(length: 16, options: ['default' => ''])]
    private string $phone = '';

    #[ORM\Column(length: 120, options: ['default' => ''])]
    private string $street = '';

    #[ORM\Column(length: 5, options: ['default' => ''])]
    private string $postalCode = '';

    #[ORM\Column(length: 80, options: ['default' => ''])]
    private string $municipality = '';

    #[ORM\Column(length: 40, options: ['default' => ''])]
    private string $province = '';

    public function apply(Firm $firm): void
    {
        $this->id = $firm->id();
        $this->name = $firm->name();
        $this->taxId = $firm->contact()->taxId();
        $this->phone = $firm->contact()->phone();
        $this->street = $firm->contact()->street();
        $this->postalCode = $firm->contact()->postalCode();
        $this->municipality = $firm->contact()->municipality();
        $this->province = $firm->contact()->province();
    }

    public function toDomain(): Firm
    {
        return new Firm(
            $this->id,
            $this->name,
            FiscalContact::stored($this->taxId, $this->phone, $this->street, $this->postalCode, $this->municipality, $this->province),
        );
    }
}
