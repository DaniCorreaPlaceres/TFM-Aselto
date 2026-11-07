<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientProfileLocked;
use App\Cliente\Domain\ClientRepository;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\Transaction;

final class SaveClientFiscalProfile
{
    public function __construct(
        private readonly OpenClientFiscalProfile $profiles,
        private readonly ClientRepository $clients,
        private readonly Transaction $transaction,
    ) {
    }

    public function own(string $userId, FiscalContact $contact): void
    {
        $this->save($this->profiles->forOwner($userId), $contact);
    }

    public function byActor(string $actorId, string $clientId, FiscalContact $contact): void
    {
        $this->save($this->profiles->forActor($actorId, $clientId), $contact);
    }

    private function save(ClientFiscalAccess $access, FiscalContact $contact): void
    {
        if (!$access->editable()) {
            throw new ClientProfileLocked('The fiscal profile cannot be changed.');
        }

        $client = $access->client()->changeContact($contact);
        $this->transaction->run(function () use ($client): void {
            $this->clients->save($client);
        });
    }
}
