<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientRepository;
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
    public function execute(?string $firmId): array
    {
        $records = null === $firmId
            ? $this->clients->findWithoutFirm()
            : $this->clients->findByFirm($firmId);
        $listings = [];

        foreach ($records as $client) {
            $user = $this->users->find($client->userId());

            if (null === $user) {
                continue;
            }

            $listings[] = new ClientListing($client->id(), $client->name(), $client->kind(), $user->email());
        }

        return $listings;
    }
}
