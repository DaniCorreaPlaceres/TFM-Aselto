<?php

namespace App\Comunicacion\Domain;

interface ConversationRepository
{
    public function find(string $id): ?Conversation;

    /** @return list<Conversation> */
    public function forOwner(string $ownerId): array;

    /** @return list<Conversation> */
    public function forFirm(string $firmId): array;

    public function save(Conversation $conversation): void;
}
