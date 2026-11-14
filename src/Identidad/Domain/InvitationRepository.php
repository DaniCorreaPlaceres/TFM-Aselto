<?php

namespace App\Identidad\Domain;

interface InvitationRepository
{
    /** @return list<PlatformInvitation> */
    public function all(): array;

    public function findByHash(string $hash): ?PlatformInvitation;

    public function save(PlatformInvitation $invitation): void;

    /** @param callable(PlatformInvitation): void $operation */
    public function locked(string $id, callable $operation): void;
}
