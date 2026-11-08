<?php

namespace App\Documento\Application;

use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Documento\Domain\InvalidWithholding;
use App\Documento\Domain\WithholdingRate;

final class DocumentWithholdingPolicy
{
    public function __construct(private readonly ClientRepository $clients)
    {
    }

    public function allows(string $ownerId): bool
    {
        return ClientKind::SelfEmployed === $this->clients->findByUserId($ownerId)?->kind();
    }

    public function assertAllowed(string $ownerId, ?WithholdingRate $rate): void
    {
        if (null !== $rate && !$this->allows($ownerId)) {
            throw new InvalidWithholding('Withholding is only available to self-employed clients.');
        }
    }
}
