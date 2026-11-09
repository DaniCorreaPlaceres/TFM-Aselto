<?php

namespace App\Finanzas\Infrastructure\Persistence;

use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final class DoctrineQuarterRepository implements QuarterRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $ownerId, ?string $firmId, QuarterPeriod $period): ?Quarter
    {
        $record = $this->entityManager->find(DoctrineQuarter::class, ['ownerId' => $ownerId, 'scope' => $firmId ?? 'personal', 'year' => $period->year, 'number' => $period->number]);
        if (!$record instanceof DoctrineQuarter) {
            return null;
        }
        $this->entityManager->refresh($record);

        return $record->toDomain();
    }

    public function forOwner(string $ownerId): array
    {
        $records = $this->entityManager->getRepository(DoctrineQuarter::class)->findBy(['ownerId' => $ownerId], ['year' => 'DESC', 'number' => 'DESC', 'scope' => 'ASC']);

        return array_map(function (DoctrineQuarter $record): Quarter {
            $this->entityManager->refresh($record);

            return $record->toDomain();
        }, $records);
    }

    public function save(Quarter $quarter): void
    {
        try {
            $record = $this->entityManager->find(DoctrineQuarter::class, ['ownerId' => $quarter->ownerId, 'scope' => $quarter->firmId ?? 'personal', 'year' => $quarter->period->year, 'number' => $quarter->period->number]);
            if (!$record instanceof DoctrineQuarter) {
                $record = new DoctrineQuarter();
            } else {
                $this->entityManager->lock($record, LockMode::OPTIMISTIC, $quarter->version);
            }
            $record->apply($quarter);
            $this->entityManager->persist($record);
            $this->entityManager->flush();
        } catch (OptimisticLockException|UniqueConstraintViolationException) {
            throw new QuarterChanged('The quarter has changed.');
        }
    }
}
