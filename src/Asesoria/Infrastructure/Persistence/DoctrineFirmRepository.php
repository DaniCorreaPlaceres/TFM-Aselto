<?php

namespace App\Asesoria\Infrastructure\Persistence;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use Doctrine\ORM\EntityManagerInterface;

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
        }

        $record->apply($firm);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }
}
