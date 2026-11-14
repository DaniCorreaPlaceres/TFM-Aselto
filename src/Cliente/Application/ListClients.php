<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class ListClients
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @return list<ClientListing>
     */
    public function execute(string $actorId): array
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || Role::Client === $actor->role() || (Role::Superadmin !== $actor->role() && null === $actor->firmId())) {
            throw new UnknownClient('Client list not available.');
        }
        $firmId = Role::Superadmin === $actor->role() ? null : $actor->firmId();
        $records = null === $firmId
            ? $this->clients->findWithoutFirm()
            : $this->clients->findByFirm($firmId);
        $listings = [];

        foreach ($records as $client) {
            $user = $this->users->find($client->userId());

            if (null === $user || Role::Client !== $user->role() || $user->firmId() !== $client->firmId() || $client->firmId() !== $firmId) {
                continue;
            }

            $listings[] = new ClientListing($client->id(), $client->name(), $client->kind(), $user->email());
        }

        return $listings;
    }
}
