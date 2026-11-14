<?php

namespace App\Asesoria\Infrastructure\Persistence;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmChanged;
use App\Asesoria\Domain\FirmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

final class DoctrineFirmRepository implements FirmRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(string $id): ?Firm
    {
        $record = $this->entityManager->find(DoctrineFirm::class, $id);

        return $record instanceof DoctrineFirm ? $record->toDomain() : null;
    }

    public function all(): array
    {
        $firms = [];

        foreach ($this->entityManager->getRepository(DoctrineFirm::class)->findBy([], ['name' => 'ASC']) as $record) {
            $firms[] = $record->toDomain();
        }

        return $firms;
    }

    public function save(Firm $firm): void
    {
        $record = $this->entityManager->find(DoctrineFirm::class, $firm->id());

        if (!$record instanceof DoctrineFirm) {
            $record = new DoctrineFirm();
        } elseif ($record->version() !== $firm->version()) {
            throw new FirmChanged('The firm changed.');
        }

        $record->apply($firm);
        $this->entityManager->persist($record);
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException $error) {
            throw new FirmChanged('The firm changed.', previous: $error);
        }
    }
}
