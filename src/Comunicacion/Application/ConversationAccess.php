<?php

namespace App\Comunicacion\Application;

use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientRepository;
use App\Comunicacion\Domain\Conversation;
use App\Comunicacion\Domain\ConversationNotFound;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;

final class ConversationAccess
{
    public function __construct(private readonly UserRepository $users, private readonly ClientRepository $clients)
    {
    }

    public function actor(string $actorId): User
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || !in_array($actor->role(), [Role::Client, Role::FirmAdmin, Role::Advisor], true)) {
            throw new ConversationNotFound('The conversation is not available.');
        }

        return $actor;
    }

    public function client(string $actorId, string $ownerId): Client
    {
        $actor = $this->actor($actorId);
        $client = $this->clients->findByUserId($ownerId);
        if (null === $client || null === $client->firmId() || (Role::Client === $actor->role() ? $ownerId !== $actorId || $actor->firmId() !== $client->firmId() : $actor->firmId() !== $client->firmId())) {
            throw new ConversationNotFound('The conversation is not available.');
        }

        return $client;
    }

    public function assertConversation(string $actorId, Conversation $conversation): void
    {
        if ($this->client($actorId, $conversation->ownerId)->firmId() !== $conversation->firmId) {
            throw new ConversationNotFound('The conversation is not available.');
        }
    }

    public function assertStaff(string $actorId): void
    {
        if (!in_array($this->actor($actorId)->role(), [Role::FirmAdmin, Role::Advisor], true)) {
            throw new ConversationNotFound('The request cannot be managed by this actor.');
        }
    }
}
