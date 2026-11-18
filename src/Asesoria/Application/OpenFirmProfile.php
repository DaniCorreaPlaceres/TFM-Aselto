<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\MissingFirm;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class OpenFirmProfile
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly UserRepository $users,
    ) {
    }

    public function execute(string $actorId): Firm
    {
        $actor = $this->users->find($actorId);

        if (null === $actor || !$actor->isActivated() || Role::FirmAdmin !== $actor->role() || null === $actor->firmId()) {
            throw new FirmProfileDenied('The firm profile cannot be opened.');
        }

        return $this->firms->find($actor->firmId()) ?? throw new MissingFirm('The firm does not exist.');
    }
}
