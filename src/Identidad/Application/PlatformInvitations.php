<?php

namespace App\Identidad\Application;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\InvitationNotifier;
use App\Identidad\Domain\InvitationRepository;
use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\PasswordTooShort;
use App\Identidad\Domain\PlatformInvitation;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\InvalidFiscalContact;

final class PlatformInvitations
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly FirmRepository $firms,
        private readonly ClientRepository $clients,
        private readonly InvitationNotifier $notifier,
        private readonly TokenGenerator $tokens,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly PasswordHasher $passwords,
    ) {
    }

    /** @return list<InvitationSummary> */
    public function list(string $actorId): array
    {
        $this->assertAdmin($actorId);
        $rows = [];
        foreach ($this->invitations->all() as $invitation) {
            $existing = null !== $this->users->findByEmail($invitation->email);
            $status = null !== $invitation->acceptedAt ? 'Aceptada' : ($existing ? 'Correo ya registrado' : ($invitation->isUsable($this->clock->now()) ? 'Pendiente' : 'Caducada'));
            $rows[] = new InvitationSummary($invitation->id, $invitation->email, $invitation->target, $invitation->sentAt, $invitation->expiresAt, $status, null === $invitation->acceptedAt && !$existing);
        }

        return $rows;
    }

    public function send(string $actorId, string $email, InvitationTarget $target): void
    {
        $this->assertAdmin($actorId);
        $raw = $this->tokens->next();
        $now = $this->clock->now();
        $invitation = new PlatformInvitation($this->ids->next(), $email, $target, hash('sha256', $raw), $now, $now->modify('+24 hours'));
        $this->assertNewEmail($invitation->email);
        $this->invitations->save($invitation);
        $this->notifier->send($invitation->email, $target, $raw);
    }

    public function resend(string $actorId, string $id): void
    {
        $this->assertAdmin($actorId);
        $raw = $this->tokens->next();
        $recipient = null;
        $this->invitations->locked($id, function (PlatformInvitation $invitation) use ($raw, &$recipient): void {
            $this->assertNewEmail($invitation->email);
            $recipient = $invitation->resend(hash('sha256', $raw), $this->clock->now());
            $this->invitations->save($recipient);
        });
        if (null !== $recipient) {
            $this->notifier->send($recipient->email, $recipient->target, $raw);
        }
    }

    public function inspect(string $raw): PlatformInvitation
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $raw)) {
            throw new InvitationUnavailable('Invalid invitation.');
        }
        $invitation = $this->invitations->findByHash(hash('sha256', $raw));
        if (null === $invitation || !$invitation->isUsable($this->clock->now()) || null !== $this->users->findByEmail($invitation->email)) {
            throw new InvitationUnavailable('Invitation no longer available.');
        }

        return $invitation;
    }

    public function accept(string $raw, string $name, FiscalContact $contact, string $password): void
    {
        $invitation = $this->inspect($raw);
        if (!$contact->isComplete()) {
            throw new InvalidFiscalContact(InvalidFiscalContact::TAX_ID_MISSING);
        }
        if (mb_strlen($password) < 8) {
            throw new PasswordTooShort('The password is too short.');
        }
        $passwordHash = $this->passwords->hash($password);
        $this->invitations->locked($invitation->id, function (PlatformInvitation $current) use ($raw, $name, $contact, $passwordHash): void {
            if (!hash_equals($current->tokenHash, hash('sha256', $raw)) || !$current->isUsable($this->clock->now())) {
                throw new InvitationUnavailable('Invitation no longer available.');
            }
            $this->assertNewEmail($current->email);
            $firmId = InvitationTarget::Advisory === $current->target ? $this->ids->next() : null;
            $user = new User($this->ids->next(), $current->email, $passwordHash, null === $firmId ? Role::Client : Role::FirmAdmin, $firmId, true);
            $this->users->save($user);
            if (null !== $firmId) {
                $this->firms->save(new Firm($firmId, $name, $contact));
            } else {
                $kind = InvitationTarget::Company === $current->target ? ClientKind::Company : ClientKind::SelfEmployed;
                $this->clients->save(new Client($this->ids->next(), $user->id(), $kind, $name, null, $contact));
            }
            $this->invitations->save($current->accept($this->clock->now()));
        });
    }

    private function assertNewEmail(string $email): void
    {
        if (null !== $this->users->findByEmail($email)) {
            throw new EmailAlreadyUsed('The email already belongs to an account.');
        }
    }

    private function assertAdmin(string $actorId): void
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || Role::Superadmin !== $actor->role()) {
            throw new InvitationUnavailable('Platform invitations are not available.');
        }
    }
}
