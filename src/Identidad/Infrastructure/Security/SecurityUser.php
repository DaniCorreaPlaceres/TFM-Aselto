<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param list<string> $roles
     */
    private function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly array $roles,
        private readonly Role $role,
        private readonly ?string $firmId,
        private readonly bool $activated,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self(
            $user->id(),
            $user->email(),
            $user->passwordHash(),
            ['ROLE_USER', self::securityRole($user->role())],
            $user->role(),
            $user->firmId(),
            $user->isActivated(),
        );
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function firmId(): ?string
    {
        return $this->firmId;
    }

    public function isActivated(): bool
    {
        return $this->activated;
    }

    public function eraseCredentials(): void
    {
    }

    private static function securityRole(Role $role): string
    {
        return match ($role) {
            Role::Superadmin => 'ROLE_SUPERADMIN',
            Role::FirmAdmin => 'ROLE_FIRM_ADMIN',
            Role::Advisor => 'ROLE_ADVISOR',
            Role::Client => 'ROLE_CLIENT',
        };
    }
}
