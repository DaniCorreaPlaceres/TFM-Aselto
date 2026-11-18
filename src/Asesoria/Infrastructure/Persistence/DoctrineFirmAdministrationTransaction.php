<?php

namespace App\Asesoria\Infrastructure\Persistence;

use App\Asesoria\Domain\FirmAdministrationTransaction;
use App\Asesoria\Domain\MissingFirm;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\Role;
use App\Identidad\Infrastructure\Persistence\DoctrineUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFirmAdministrationTransaction implements FirmAdministrationTransaction
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function run(string $firmId, callable $operation): void
    {
        try {
            $this->em->wrapInTransaction(function () use ($firmId, $operation): void {
                $firm = $this->em->find(DoctrineFirm::class, $firmId) ?? throw new MissingFirm('Firm not found.');
                $this->em->refresh($firm, LockMode::PESSIMISTIC_WRITE);
                foreach ($this->em->getRepository(DoctrineUser::class)->findBy(['firmId' => $firmId, 'role' => Role::FirmAdmin], ['id' => 'ASC']) as $admin) {
                    $this->em->refresh($admin, LockMode::PESSIMISTIC_WRITE);
                }
                $operation();
            });
        } catch (UniqueConstraintViolationException $error) {
            throw new EmailAlreadyUsed('Email already assigned.', previous: $error);
        }
    }
}
