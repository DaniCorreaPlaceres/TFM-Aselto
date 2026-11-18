<?php

namespace App\Shared\Application;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\RecordCatalog;
use App\Shared\Domain\RecordCriteria;
use App\Shared\Domain\RecordPage;

final readonly class BrowseRecords
{
    public function __construct(private UserRepository $users, private RecordCatalog $catalog, private \App\Cliente\Domain\ClientRepository $clients)
    {
    }

    public function execute(string $actorId, string $kind, RecordCriteria $criteria): RecordPage
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated()) {
            throw new \DomainException('Listing unavailable.');
        }
        $scope = match ($kind) {
            'documents', 'pending' => Role::Client === $actor->role() ? $actor->id() : null,
            'inbox', 'clients' => in_array($actor->role(), [Role::FirmAdmin, Role::Advisor], true) ? $actor->firmId() : null,
            'team' => Role::FirmAdmin === $actor->role() ? $actor->firmId() : null,
            'conversations' => in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true) ? $actor->id() : null,
            'recipients', 'shared_documents' => in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true) ? $actor->id() : null,
            'independent', 'firms', 'invitations' => Role::Superadmin === $actor->role() ? 'platform' : null,
            default => null,
        };
        if (null === $scope) {
            throw new \DomainException('Listing unavailable.');
        }
        if ('shared_documents' === $kind) {
            $client = $this->clients->findByUserId($criteria->owner);
            if (null === $client || null === $client->firmId() || $client->firmId() !== $actor->firmId() || (Role::Client === $actor->role() && $client->userId() !== $actorId)) {
                throw new \DomainException('Listing unavailable.');
            }
        }

        return $this->catalog->page($kind, $scope, $criteria);
    }

    /** @return array{unread: int, requests: int} */
    public function attention(string $actorId): array
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || !in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true)) {
            return ['unread' => 0, 'requests' => 0];
        }

        return $this->catalog->attention($actorId);
    }

    /** @template T
     * @param \Closure(): T $read
     *
     * @return T
     */
    public function snapshot(\Closure $read): mixed
    {
        return $this->catalog->snapshot($read);
    }
}
