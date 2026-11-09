<?php

namespace App\Finanzas\Application;

use App\Cliente\Domain\ClientRepository;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterNotFound;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class QuarterAccess
{
    public function __construct(private readonly UserRepository $users, private readonly ClientRepository $clients)
    {
    }

    public function resolve(string $actorId, ?string $clientId): QuarterAccessData
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated()) {
            throw new QuarterNotFound('The quarter does not exist.');
        }
        if (null === $clientId) {
            $client = $this->clients->findByUserId($actorId);
            if (Role::Client !== $actor->role() || null === $client) {
                throw new QuarterNotFound('The quarter does not exist.');
            }

            return new QuarterAccessData($client->userId(), $client->id(), $client->name(), $client->firmId(), true);
        }
        $client = $this->clients->find($clientId);
        if (!in_array($actor->role(), [Role::FirmAdmin, Role::Advisor], true) || null === $actor->firmId() || null === $client || $client->firmId() !== $actor->firmId()) {
            throw new QuarterNotFound('The quarter does not exist.');
        }

        return new QuarterAccessData($client->userId(), $client->id(), $client->name(), $client->firmId(), false);
    }

    /** @param list<Quarter> $history */
    public function scope(QuarterAccessData $access, ?string $scope, array $history): ?string
    {
        $current = $access->firmId ?? 'personal';
        $scope ??= $current;
        if ($scope === $current) {
            return $access->firmId;
        }
        foreach ($history as $quarter) {
            if (($quarter->firmId ?? 'personal') === $scope && $access->mayRead($quarter)) {
                return $quarter->firmId;
            }
        }
        throw new QuarterNotFound('The quarter scope is not available.');
    }
}
