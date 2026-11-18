<?php

namespace App\Asesoria\Application;

use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;

final class ListTeam
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return list<User>
     */
    public function execute(string $firmId): array
    {
        return $this->users->findByFirm($firmId);
    }
}
