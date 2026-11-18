<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
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
        private readonly int $authVersion,
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
            $user->authVersion(),
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

    public function authVersion(): int
    {
        return $this->authVersion;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        // Sessions serialized before authVersion existed must be reauthenticated.
        $version = get_object_vars($this)['authVersion'] ?? null;
        $otherVersion = $user instanceof self ? (get_object_vars($user)['authVersion'] ?? null) : null;

        return $user instanceof self && is_int($version) && $version === $otherVersion
            && $this->id === $user->id && $this->email === $user->email
            && $this->passwordHash === $user->passwordHash
            && $this->role === $user->role && $this->firmId === $user->firmId && $this->activated === $user->activated;
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
