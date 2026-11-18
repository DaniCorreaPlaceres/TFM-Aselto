<?php

namespace App\Cliente\Domain;

use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\InvalidFiscalContact;

final class Client
{
    private readonly FiscalContact $contact;

    public function __construct(
        private readonly string $id,
        private readonly string $userId,
        private readonly ClientKind $kind,
        private string $name,
        private readonly ?string $firmId,
        ?FiscalContact $contact = null,
        private readonly int $version = 1,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidClient('The id is not a valid UUID.');
        }

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $userId)) {
            throw new InvalidClient('The user id is not a valid UUID.');
        }

        if (null !== $firmId && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $firmId)) {
            throw new InvalidClient('The firm id is not a valid UUID.');
        }

        $this->name = self::normalizeName($name);
        $this->contact = $contact ?? FiscalContact::blank();
    }

    public function id(): string
    {
        return $this->id;
    }

    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 120) {
            throw new InvalidClient('The client name is empty or too long.');
        }

        return $name;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function kind(): ClientKind
    {
        return $this->kind;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function firmId(): ?string
    {
        return $this->firmId;
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

        return new self($this->id, $this->userId, $this->kind, $this->name, $this->firmId, $contact, $this->version);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function changeDetails(string $name, FiscalContact $contact): self
    {
        $this->changeContact($contact);

        return new self($this->id, $this->userId, $this->kind, $name, $this->firmId, $contact, $this->version);
    }
}
