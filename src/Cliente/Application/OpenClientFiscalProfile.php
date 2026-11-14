<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class OpenClientFiscalProfile
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly UserRepository $users,
    ) {
    }

    public function forOwner(string $userId): ClientFiscalAccess
    {
        $user = $this->users->find($userId);
        $client = $this->clients->findByUserId($userId);

        if (null === $user || !$user->isActivated() || null === $client || Role::Client !== $user->role() || $user->firmId() !== $client->firmId()) {
            throw new UnknownClient('The client does not exist.');
        }

        return new ClientFiscalAccess($client, $user->email(), true);
    }

    public function forActor(string $actorId, string $clientId): ClientFiscalAccess
    {
        $actor = $this->users->find($actorId);
        $client = $this->clients->find($clientId);

        if (null === $actor || !$actor->isActivated() || null === $client) {
            throw new UnknownClient('The client does not exist.');
        }

        $owner = $this->users->find($client->userId());

        if (null === $owner || Role::Client !== $owner->role() || $owner->firmId() !== $client->firmId()) {
            throw new UnknownClient('The client does not exist.');
        }

        $sameFirm = null !== $client->firmId() && $client->firmId() === $actor->firmId();
        $visible = match ($actor->role()) {
            Role::FirmAdmin, Role::Advisor => $sameFirm,
            Role::Superadmin => null === $client->firmId(),
            Role::Client => false,
        };

        if (!$visible) {
            throw new UnknownClient('The client does not exist.');
        }

        return new ClientFiscalAccess($client, $owner->email(), Role::FirmAdmin === $actor->role());
    }
}
