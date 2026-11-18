<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\FirmRepository;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\UserRepository;

final class ListFirms
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @return list<FirmSummary>
     */
    public function execute(string $actorId): array
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || Role::Superadmin !== $actor->role()) {
            throw new FirmProfileDenied('Administrative access denied.');
        }
        $summaries = [];

        foreach ($this->firms->all() as $firm) {
            $adminEmails = [];

            foreach ($this->users->findByFirm($firm->id()) as $user) {
                if (Role::FirmAdmin === $user->role()) {
                    $adminEmails[] = $user->email();
                }
            }

            $summaries[] = new FirmSummary($firm->name(), 1 === count($adminEmails) ? $adminEmails[0] : '', $firm->id());
        }

        return $summaries;
    }
}
