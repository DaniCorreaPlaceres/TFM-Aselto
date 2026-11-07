<?php

namespace App\Cliente\Domain;

final class Client
{
    public function __construct(
        private readonly string $id,
        private readonly string $userId,
        private readonly ClientKind $kind,
        private string $name,
        private readonly ?string $firmId,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id)) {
            throw new InvalidClient('The id is not a valid UUID.');
        }

        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $userId)) {
            throw new InvalidClient('The user id is not a valid UUID.');
        }

        $name = trim($name);

        if ('' === $name || mb_strlen($name) > 120) {
            throw new InvalidClient('The client name is empty or too long.');
        }

        if (null !== $firmId && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $firmId)) {
            throw new InvalidClient('The firm id is not a valid UUID.');
        }

        $this->name = $name;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function kind(): ClientKind
    {
        return $this->kind;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function firmId(): ?string
    {
        return $this->firmId;
    }
}
