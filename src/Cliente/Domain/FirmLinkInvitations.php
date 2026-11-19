<?php

namespace App\Cliente\Domain;

interface FirmLinkInvitations
{
    public function findByHash(string $hash): ?FirmLinkInvitation;

    public function forPair(string $ownerId, string $firmId): ?FirmLinkInvitation;

    /** @return list<FirmLinkInvitation> */
    public function forFirm(string $firmId): array;

    public function save(FirmLinkInvitation $invitation): void;
}
