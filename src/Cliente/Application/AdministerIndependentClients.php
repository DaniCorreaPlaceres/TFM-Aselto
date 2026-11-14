<?php

namespace App\Cliente\Application;

use App\Cliente\Domain\ClientChanged;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\UnknownClient;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\FiscalContact;

final class AdministerIndependentClients
{
    public function __construct(private readonly ClientRepository $clients, private readonly UserRepository $users, private readonly ClientTransaction $transaction, private readonly AccountTransaction $accounts)
    {
    }

    public function open(string $actorId, string $clientId): AdministrativeClient
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || Role::Superadmin !== $actor->role()) {
            throw new UnknownClient('Client not available.');
        }
        $client = $this->clients->find($clientId);
        $owner = null === $client ? null : $this->users->find($client->userId());
        if (null === $client || null !== $client->firmId() || null === $owner || Role::Client !== $owner->role() || null !== $owner->firmId()) {
            throw new UnknownClient('Client not available.');
        }

        return new AdministrativeClient($client, $owner->email(), $owner->isActivated());
    }

    public function save(string $actorId, string $clientId, int $version, string $name, FiscalContact $contact): void
    {
        $view = $this->open($actorId, $clientId);
        $ownerId = $view->client->userId();
        $this->transaction->run($ownerId, function () use ($ownerId, $actorId, $clientId, $version, $name, $contact): void {
            $this->accounts->forUser($ownerId, function () use ($actorId, $clientId, $version, $name, $contact): void {
                $current = $this->open($actorId, $clientId);
                if ($version !== $current->client->version()) {
                    throw new ClientChanged('Reload client profile.');
                }
                $this->clients->save($current->client->changeDetails($name, $contact));
            });
        });
    }
}
