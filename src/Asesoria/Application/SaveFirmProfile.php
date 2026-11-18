<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\FirmChanged;
use App\Asesoria\Domain\FirmRepository;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\Transaction;

final class SaveFirmProfile
{
    public function __construct(
        private readonly OpenFirmProfile $profiles,
        private readonly FirmRepository $firms,
        private readonly Transaction $transaction,
    ) {
    }

    public function execute(string $actorId, FiscalContact $contact, ?int $expectedVersion = null): void
    {
        $firm = $this->profiles->execute($actorId)->changeContact($contact);
        if (null !== $expectedVersion && $firm->version() !== $expectedVersion) {
            throw new FirmChanged('The firm changed.');
        }
        $this->transaction->run(function () use ($firm): void {
            $this->firms->save($firm);
        });
    }
}
