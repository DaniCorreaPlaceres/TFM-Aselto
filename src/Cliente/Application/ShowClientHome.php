<?php

namespace App\Cliente\Application;

use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\ClientRepository;

final class ShowClientHome
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly FirmRepository $firms,
    ) {
    }

    public function execute(string $userId): ClientHome
    {
        $client = $this->clients->findByUserId($userId);

        if (null === $client) {
            return new ClientHome(null, null);
        }

        $firmName = null;

        if (null !== $client->firmId()) {
            $firmName = $this->firms->find($client->firmId())?->name();
        }

        return new ClientHome($client->name(), $firmName);
    }
}
