<?php

namespace App\Cliente\Application;

use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\ClientRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class ShowClientFirmContact
{
    public function __construct(private readonly UserRepository $users, private readonly ClientRepository $clients, private readonly FirmRepository $firms)
    {
    }

    public function execute(string $actorId): ?ClientFirmContact
    {
        $actor = $this->users->find($actorId);
        $client = $this->clients->findByUserId($actorId);
        if (null === $actor || !$actor->isActivated() || Role::Client !== $actor->role() || null === $client || null === $client->firmId() || $actor->firmId() !== $client->firmId()) {
            return null;
        }
        $firm = $this->firms->find($client->firmId());
        if (null === $firm) {
            return null;
        }
        $email = null;
        foreach ($this->users->findByFirm($firm->id()) as $member) {
            if ($member->firmId() === $firm->id() && Role::FirmAdmin === $member->role() && $member->isActivated()) {
                $email = $member->email();
                break;
            }
        }
        $contact = $firm->contact();

        return new ClientFirmContact($firm->name(), $email, $contact->phone(), $contact->street(), $contact->postalCode(), $contact->municipality(), $contact->province());
    }
}
