<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\Firm;
use App\Identidad\Domain\AdminEmailChange;
use App\Identidad\Domain\User;

final readonly class AdministrativeFirm
{
    public function __construct(public Firm $firm, public ?User $admin, public ?AdminEmailChange $emailChange)
    {
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->firm->id(), $this->firm->version(), $this->admin?->id(), $this->admin?->email(), $this->admin?->authVersion(), $this->emailChange?->tokenHash], JSON_THROW_ON_ERROR));
    }
}
