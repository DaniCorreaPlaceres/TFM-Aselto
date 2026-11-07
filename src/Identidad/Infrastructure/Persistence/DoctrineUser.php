<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'identity_user')]
class DoctrineUser
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 32, enumType: Role::class)]
    private Role $role;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $firmId = null;

    #[ORM\Column]
    private bool $activated = false;

    public function apply(User $user): void
    {
        $this->id = $user->id();
        $this->email = $user->email();
        $this->passwordHash = $user->passwordHash();
        $this->role = $user->role();
        $this->firmId = $user->firmId();
        $this->activated = $user->isActivated();
    }

    public function toDomain(): User
    {
        return new User($this->id, $this->email, $this->passwordHash, $this->role, $this->firmId, $this->activated);
    }
}
