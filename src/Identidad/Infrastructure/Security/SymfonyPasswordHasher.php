<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function hash(string $plainPassword): string
    {
        $placeholder = SecurityUser::fromDomain(new User(
            '00000000-0000-4000-8000-000000000000',
            'hash@example.test',
            'pending',
            Role::Superadmin,
            null,
        ));

        return $this->hasher->hashPassword($placeholder, $plainPassword);
    }
}
