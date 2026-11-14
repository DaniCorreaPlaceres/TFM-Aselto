<?php

namespace App\Shared\Application;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\LicensePlan;
use App\Shared\Domain\LicenseRepository;
use App\Shared\Domain\LicenseUnavailable;
use App\Shared\Domain\MonthlyQuota;

final readonly class ManageLicenses
{
    public function __construct(private UserRepository $users, private LicenseRepository $licenses, private Clock $clock)
    {
    }

    /** @return list<array{id: string, kind: string, name: string, plan: string}> */
    public function subjects(string $actorId): array
    {
        $this->admin($actorId);

        return $this->licenses->subjects();
    }

    public function assign(string $actorId, string $kind, string $id, LicensePlan $plan): void
    {
        $this->admin($actorId);
        if (!in_array($kind, ['client', 'firm'], true) || ('client' === $kind && LicensePlan::Advisory === $plan) || ('firm' === $kind && LicensePlan::Professional === $plan)) {
            throw new LicenseUnavailable('Invalid license destination.');
        }
        $this->licenses->assign($kind, $id, $plan, $actorId, $this->clock->now());
    }

    /** @return MonthlyQuota|array{plan: string, clients: int, reference_cents: int} */
    public function own(string $actorId): MonthlyQuota|array
    {
        $user = $this->actor($actorId);
        if (Role::Client === $user->role()) {
            return $this->licenses->quota($user->id(), $this->clock->now());
        }
        if (in_array($user->role(), [Role::FirmAdmin, Role::Advisor], true) && null !== $user->firmId()) {
            return $this->licenses->firm($user->firmId());
        }

        throw new LicenseUnavailable('No personal license for this role.');
    }

    private function admin(string $actorId): void
    {
        if (Role::Superadmin !== $this->actor($actorId)->role()) {
            throw new LicenseUnavailable('Platform administrator required.');
        }
    }

    private function actor(string $id): User
    {
        $user = $this->users->find($id);
        if (null === $user || !$user->isActivated()) {
            throw new LicenseUnavailable('Active account required.');
        }

        return $user;
    }
}
