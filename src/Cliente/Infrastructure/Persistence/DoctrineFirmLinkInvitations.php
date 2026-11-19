<?php

namespace App\Cliente\Infrastructure\Persistence;

use App\Cliente\Domain\FirmLinkInvitation;
use App\Cliente\Domain\FirmLinkInvitations;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFirmLinkInvitations implements FirmLinkInvitations
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByHash(string $hash): ?FirmLinkInvitation
    {
        $record = $this->em->getRepository(DoctrineFirmLinkInvitation::class)->findOneBy(['tokenHash' => $hash]);
        if (null !== $record) {
            $this->em->refresh($record);
        }

        return $record?->toDomain();
    }

    public function forPair(string $ownerId, string $firmId): ?FirmLinkInvitation
    {
        $record = $this->em->getRepository(DoctrineFirmLinkInvitation::class)->findOneBy(['ownerId' => $ownerId, 'firmId' => $firmId]);
        if (null !== $record) {
            $this->em->refresh($record);
        }

        return $record?->toDomain();
    }

    public function forFirm(string $firmId): array
    {
        return array_map(static fn (DoctrineFirmLinkInvitation $row): FirmLinkInvitation => $row->toDomain(), $this->em->getRepository(DoctrineFirmLinkInvitation::class)->findBy(['firmId' => $firmId], ['sentAt' => 'DESC'], 100));
    }

    public function save(FirmLinkInvitation $invitation): void
    {
        $record = $this->em->find(DoctrineFirmLinkInvitation::class, $invitation->id) ?? new DoctrineFirmLinkInvitation();
        $record->apply($invitation);
        $this->em->persist($record);
        $this->em->flush();
    }
}
