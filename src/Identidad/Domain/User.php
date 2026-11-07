<?php

namespace App\Identidad\Domain;

final class User
{
    public function __construct(
        private readonly string $id,
        private string $email,
        private readonly string $passwordHash,
        private readonly Role $role,
        private readonly ?string $firmId,
        private readonly bool $activated = false,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidUser('The id is not a valid UUID.');
        }

        if ('' === $passwordHash) {
            throw new InvalidUser('The password hash is empty.');
        }

        $this->email = mb_strtolower(trim($email));

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidUser('The email is not valid.');
        }

        if (null !== $firmId && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $firmId)) {
            throw new InvalidUser('The firm id is not a valid UUID.');
        }

        if (Role::Superadmin === $role && null !== $firmId) {
            throw new InvalidUser('A platform admin does not belong to a firm.');
        }

        if ((Role::FirmAdmin === $role || Role::Advisor === $role) && null === $firmId) {
            throw new InvalidUser('This role belongs to a firm.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
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

    public function withPassword(string $passwordHash): self
    {
        return new self($this->id, $this->email, $passwordHash, $this->role, $this->firmId, true);
    }
}
