<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\InvalidFirm;
use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\Role;

final class InviteAdvisor
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly StartAccount $accounts,
    ) {
    }

    public function execute(string $firmId, string $email): void
    {
        if (null === $this->firms->find($firmId)) {
            throw new InvalidFirm('The firm does not exist.');
        }

        $this->accounts->open($email, Role::Advisor, $firmId);
    }
}
