<?php

namespace App\Identidad\Infrastructure\Persistence;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'identity_user')]
#[ORM\UniqueConstraint(name: 'uniq_identity_user_email', columns: ['email'])]
class DoctrineUser
{
    #[ORM\Id]
    #[ORM\Column(length: 36, options: ['fixed' => true])]
    private string $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 32, enumType: Role::class)]
    private Role $role;

    #[ORM\Column(length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $firmId = null;

    #[ORM\Column(options: ['default' => 0])]
    private bool $activated = false;

    #[ORM\Column(options: ['default' => 1])]
    private int $authVersion = 1;

    public function apply(User $user): void
    {
        $this->id = $user->id();
        $this->email = $user->email();
        $this->passwordHash = $user->passwordHash();
        $this->role = $user->role();
        $this->firmId = $user->firmId();
        $this->activated = $user->isActivated();
        $this->authVersion = $user->authVersion();
    }

    public function toDomain(): User
    {
        return new User($this->id, $this->email, $this->passwordHash, $this->role, $this->firmId, $this->activated, $this->authVersion);
    }
}
