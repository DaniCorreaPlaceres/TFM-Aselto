<?php

namespace App\Cliente\Infrastructure\Persistence;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Shared\Domain\FiscalContact;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'client_profile')]
class DoctrineClient
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 36, unique: true)]
    private string $userId;

    #[ORM\Column(length: 32)]
    private string $kind;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $firmId = null;

    #[ORM\Column(length: 16)]
    private string $taxId = '';

    #[ORM\Column(length: 16)]
    private string $phone = '';

    #[ORM\Column(length: 120)]
    private string $street = '';

    #[ORM\Column(length: 5)]
    private string $postalCode = '';

    #[ORM\Column(length: 80)]
    private string $municipality = '';

    #[ORM\Column(length: 40)]
    private string $province = '';

    public function apply(Client $client): void
    {
        $this->id = $client->id();
        $this->userId = $client->userId();
        $this->kind = $client->kind()->value;
        $this->name = $client->name();
        $this->firmId = $client->firmId();
        $this->taxId = $client->contact()->taxId();
        $this->phone = $client->contact()->phone();
        $this->street = $client->contact()->street();
        $this->postalCode = $client->contact()->postalCode();
        $this->municipality = $client->contact()->municipality();
        $this->province = $client->contact()->province();
    }

    public function toDomain(): Client
    {
        return new Client(
            $this->id,
            $this->userId,
            ClientKind::from($this->kind),
            $this->name,
            $this->firmId,
            FiscalContact::stored($this->taxId, $this->phone, $this->street, $this->postalCode, $this->municipality, $this->province),
        );
    }
}
