<?php

namespace App\Finanzas\Infrastructure\Realtime;

use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: DoctrineQuarterRepository::class)]
final class NotifyingQuarterRepository implements QuarterRepository
{
    public function __construct(#[AutowireDecorated] private readonly QuarterRepository $inner, private readonly QuarterUpdateOutbox $outbox, private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $ownerId, ?string $firmId, QuarterPeriod $period): ?Quarter
    {
        return $this->inner->find($ownerId, $firmId, $period);
    }

    public function forOwner(string $ownerId): array
    {
        return $this->inner->forOwner($ownerId);
    }

    public function save(Quarter $quarter): void
    {
        $this->entityManager->getConnection()->transactional(function () use ($quarter): void {
            $this->inner->save($quarter);
            $this->outbox->record($quarter->ownerId, $quarter->firmId);
        });
    }
}
