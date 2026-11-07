<?php

namespace App\Asesoria\Application;

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
    public function execute(): array
    {
        $summaries = [];

        foreach ($this->firms->all() as $firm) {
            $adminEmail = '';

            foreach ($this->users->findByFirm($firm->id()) as $user) {
                if (Role::FirmAdmin === $user->role()) {
                    $adminEmail = $user->email();
                }
            }

            $summaries[] = new FirmSummary($firm->name(), $adminEmail);
        }

        return $summaries;
    }
}
