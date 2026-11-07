<?php

namespace App\Asesoria\Domain;

use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\InvalidFiscalContact;

final class Firm
{
    private readonly FiscalContact $contact;

    public function __construct(
        private readonly string $id,
        private string $name,
        ?FiscalContact $contact = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidFirm('The id is not a valid UUID.');
        }

        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 120) {
            throw new InvalidFirm('The firm name is empty or too long.');
        }

        $this->name = $name;
        $this->contact = $contact ?? FiscalContact::blank();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function contact(): FiscalContact
    {
        return $this->contact;
    }

    public function changeContact(FiscalContact $contact): self
    {
        if (!$contact->isComplete()) {
            throw new InvalidFiscalContact(InvalidFiscalContact::TAX_ID_MISSING);
        }

        return new self($this->id, $this->name, $contact);
    }
}
