<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientChanged;
use App\Cliente\Domain\ClientProfileLocked;
use App\Cliente\Domain\ClientRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\FiscalContact;

final class SaveClientFiscalProfile
{
    public function __construct(
        private readonly OpenClientFiscalProfile $profiles,
        private readonly ClientRepository $clients,
        private readonly ClientTransaction $transaction,
    ) {
    }

    public function own(string $userId, FiscalContact $contact, ?int $version = null): void
    {
        $this->save($userId, null, $contact, $version);
    }

    public function byActor(string $actorId, string $clientId, FiscalContact $contact, ?int $version = null): void
    {
        $this->save($actorId, $clientId, $contact, $version);
    }

    private function save(string $actorId, ?string $clientId, FiscalContact $contact, ?int $version): void
    {
        $access = null === $clientId ? $this->profiles->forOwner($actorId) : $this->profiles->forActor($actorId, $clientId);
        if (!$access->editable()) {
            throw new ClientProfileLocked('The fiscal profile cannot be changed.');
        }

        $this->transaction->run($access->client()->userId(), function () use ($actorId, $clientId, $contact, $version): void {
            $current = null === $clientId ? $this->profiles->forOwner($actorId) : $this->profiles->forActor($actorId, $clientId);
            if (!$current->editable()) {
                throw new ClientProfileLocked('The fiscal profile cannot be changed.');
            }
            if (null !== $version && $current->client()->version() !== $version) {
                throw new ClientChanged('Reload client profile.');
            }
            $this->clients->save($current->client()->changeContact($contact));
        });
    }
}
