<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\Role;
use App\Shared\Domain\IdGenerator;

final class CreateFirm
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly IdGenerator $ids,
        private readonly StartAccount $accounts,
    ) {
    }

    public function execute(string $name, string $adminEmail): Firm
    {
        $firm = new Firm($this->ids->next(), $name);
        $this->accounts->open($adminEmail, Role::FirmAdmin, $firm->id(), function () use ($firm): void {
            $this->firms->save($firm);
        });

        return $firm;
    }
}
