<?php

namespace App\Asesoria\Application;

use App\Asesoria\Domain\FirmAdministrationTransaction;
use App\Asesoria\Domain\FirmChanged;
use App\Asesoria\Domain\FirmProfileDenied;
use App\Asesoria\Domain\FirmRepository;
use App\Asesoria\Domain\MissingFirm;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\AdminEmailChange;
use App\Identidad\Domain\AdminEmailChangeRepository;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\EmailChangeNotifier;
use App\Identidad\Domain\EmailChangeUnavailable;
use App\Identidad\Domain\InvalidUser;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\FiscalContact;

final class AdministerFirms
{
    public function __construct(
        private readonly FirmRepository $firms,
        private readonly UserRepository $users,
        private readonly FirmAdministrationTransaction $transaction,
        private readonly AdminEmailChangeRepository $changes,
        private readonly AccessTokenRepository $accessTokens,
        private readonly TokenGenerator $tokens,
        private readonly Clock $clock,
        private readonly EmailChangeNotifier $notifier,
    ) {
    }

    public function open(string $actorId, string $firmId): AdministrativeFirm
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || Role::Superadmin !== $actor->role()) {
            throw new FirmProfileDenied('Administrative access denied.');
        }

        return $this->current($firmId);
    }

    public function save(string $actorId, string $firmId, string $fingerprint, string $name, FiscalContact $contact): void
    {
        $this->open($actorId, $firmId);
        $this->transaction->run($firmId, function () use ($actorId, $firmId, $fingerprint, $name, $contact): void {
            $view = $this->open($actorId, $firmId);
            $this->assertCurrent($view, $fingerprint);
            $this->firms->save($view->firm->changeDetails($name, $contact));
        });
    }

    public function requestEmail(string $actorId, string $firmId, string $fingerprint, string $email): void
    {
        $this->open($actorId, $firmId);
        if (mb_strlen(trim($email)) > 180) {
            throw new InvalidUser('Email is too long.');
        }
        $raw = $this->tokens->next();
        $recipient = '';
        $this->transaction->run($firmId, function () use ($actorId, $firmId, $fingerprint, $email, $raw, &$recipient): void {
            $view = $this->open($actorId, $firmId);
            $this->assertCurrent($view, $fingerprint);
            $admin = $view->admin ?? throw new EmailChangeUnavailable('A single admin is required.');
            if (null !== $view->emailChange?->confirmedAt && !$view->emailChange->noticeSent) {
                throw new EmailChangeUnavailable('Send the outstanding security notice first.');
            }
            $recipient = $admin->changeEmail($email)->email();
            if (null !== $this->users->findByEmail($recipient)) {
                throw new EmailAlreadyUsed('Email already assigned.');
            }
            $this->changes->save(new AdminEmailChange($admin->id(), $admin->email(), $recipient, hash('sha256', $raw), $this->clock->now()->modify('+24 hours')->getTimestamp(), $admin->authVersion()));
        });
        $this->notifier->request($recipient, $raw);
    }

    public function inspect(string $raw): AdminEmailChange
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $raw)) {
            throw new EmailChangeUnavailable('Invalid link.');
        }
        $change = $this->changes->findByHash(hash('sha256', $raw));
        $user = null === $change ? null : $this->users->find($change->userId);
        if (null === $change || null === $user || Role::FirmAdmin !== $user->role() || null === $user->firmId() || !$change->usable($user, $this->clock->now()->getTimestamp())) {
            throw new EmailChangeUnavailable('Link no longer available.');
        }

        return $change;
    }

    public function confirmEmail(string $raw): void
    {
        $change = $this->inspect($raw);
        $user = $this->users->find($change->userId);
        $firmId = $user?->firmId() ?? throw new EmailChangeUnavailable('Admin not found.');
        $this->transaction->run($firmId, function () use ($raw, $change, $firmId): void {
            $view = $this->current($firmId);
            $user = $view->admin;
            $current = $this->changes->find($change->userId);
            if (null === $user || null === $current || !hash_equals($current->tokenHash, hash('sha256', $raw)) || !$current->usable($user, $this->clock->now()->getTimestamp())) {
                throw new EmailChangeUnavailable('Link no longer available.');
            }
            if (null !== $this->users->findByEmail($current->newEmail)) {
                throw new EmailAlreadyUsed('Email already assigned.');
            }
            $this->users->save($user->changeEmail($current->newEmail));
            foreach (AccessPurpose::cases() as $purpose) {
                $this->accessTokens->discardOpen($user->id(), $purpose);
            }
            $this->changes->save($current->confirm($this->clock->now()->getTimestamp()));
        });
        $this->sendNotice($change);
    }

    public function retryNotice(string $actorId, string $firmId): void
    {
        $view = $this->open($actorId, $firmId);
        $change = $view->emailChange;
        if (null === $change || null === $change->confirmedAt || $change->noticeSent) {
            throw new EmailChangeUnavailable('No outstanding notice.');
        }
        $this->sendNotice($change);
    }

    public function cancelEmail(string $actorId, string $firmId, string $fingerprint): void
    {
        $this->open($actorId, $firmId);
        $this->transaction->run($firmId, function () use ($actorId, $firmId, $fingerprint): void {
            $view = $this->open($actorId, $firmId);
            $this->assertCurrent($view, $fingerprint);
            if (null === $view->emailChange || null !== $view->emailChange->confirmedAt) {
                throw new EmailChangeUnavailable('No pending change.');
            }
            $this->changes->delete($view->emailChange->userId);
        });
    }

    private function sendNotice(AdminEmailChange $change): void
    {
        $this->notifier->confirmed($change->sourceEmail);
        $this->changes->markNotified($change->tokenHash);
    }

    private function current(string $firmId): AdministrativeFirm
    {
        $firm = $this->firms->find($firmId) ?? throw new MissingFirm('Firm not found.');
        $admins = array_values(array_filter($this->users->findByFirm($firmId), static fn ($user): bool => Role::FirmAdmin === $user->role()));
        $admin = 1 === count($admins) ? $admins[0] : null;

        return new AdministrativeFirm($firm, $admin, null === $admin ? null : $this->changes->find($admin->id()));
    }

    private function assertCurrent(AdministrativeFirm $view, string $fingerprint): void
    {
        if (!hash_equals($view->fingerprint(), $fingerprint)) {
            throw new FirmChanged('Reload the administrative profile.');
        }
    }
}
