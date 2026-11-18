<?php

namespace App\Cliente\Application;

use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\MissingFirm;
use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\InvalidFiscalContact;

final class RegisterClient
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly FirmRepository $firms,
        private readonly IdGenerator $ids,
        private readonly StartAccount $accounts,
    ) {
    }

    public function execute(string $name, ClientKind $kind, string $email, ?string $firmId, FiscalContact $contact): Client
    {
        $name = Client::normalizeName($name);
        if (!$contact->isComplete()) {
            throw new InvalidFiscalContact(InvalidFiscalContact::TAX_ID_MISSING);
        }

        if (null !== $firmId && null === $this->firms->find($firmId)) {
            throw new MissingFirm('The firm does not exist.');
        }

        $client = null;
        $this->accounts->open($email, Role::Client, $firmId, function (User $user) use (&$client, $name, $kind, $firmId, $contact): void {
            $client = new Client($this->ids->next(), $user->id(), $kind, $name, $firmId, $contact);
            $this->clients->save($client);
        });

        if (!$client instanceof Client) {
            throw new \LogicException('The client was not created.');
        }

        return $client;
    }
}
