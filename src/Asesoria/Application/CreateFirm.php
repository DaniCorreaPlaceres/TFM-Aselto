<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\Role;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\InvalidFiscalContact;

final class CreateFirm
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly IdGenerator $ids,
        private readonly StartAccount $accounts,
    ) {
    }

    public function execute(string $name, string $adminEmail, FiscalContact $contact): Firm
    {
        if (!$contact->isComplete()) {
            throw new InvalidFiscalContact(InvalidFiscalContact::TAX_ID_MISSING);
        }

        $firm = new Firm($this->ids->next(), $name, $contact);
        $this->accounts->open($adminEmail, Role::FirmAdmin, $firm->id(), function () use ($firm): void {
            $this->firms->save($firm);
        });

        return $firm;
    }
}
