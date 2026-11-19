<?php

namespace App\Cliente\Application;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\ClientRepository;
use App\Cliente\Domain\FirmLinkInvitation;
use App\Cliente\Domain\FirmLinkInvitations;
use App\Cliente\Domain\FirmLinkNotifier;
use App\Documento\Domain\DocumentRepository;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterRepository;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\ClientTransaction;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\SecurityAudit;
use App\Shared\Domain\SecurityEvent;

final class LinkExistingClient
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ClientRepository $clients,
        private readonly FirmRepository $firms,
        private readonly FirmLinkInvitations $invitations,
        private readonly FirmLinkNotifier $notifier,
        private readonly TokenGenerator $tokens,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly ClientTransaction $transaction,
        private readonly AccountTransaction $accounts,
        private readonly DocumentRepository $documents,
        private readonly QuarterRepository $quarters,
        private readonly SecurityAudit $audit,
    ) {
    }

    /** @return list<array{email: string, status: string}> */
    public function sent(string $actorId): array
    {
        $rows = [];
        foreach ($this->invitations->forFirm($this->firm($actorId)->id()) as $invitation) {
            $available = true;
            try {
                $available = $this->independent($invitation->ownerId)->email() === $invitation->email;
            } catch (InvitationUnavailable) {
                $available = false;
            }
            $rows[] = ['email' => $invitation->email, 'status' => null !== $invitation->acceptedAt ? 'Aceptada' : (!$available ? 'No disponible' : ($invitation->usable($this->clock->now()) ? 'Pendiente' : 'Caducada'))];
        }

        return $rows;
    }

    public function send(string $actorId, string $email): void
    {
        $firm = $this->firm($actorId);
        $recipient = $this->users->findByEmail($email);
        if (null === $recipient) {
            throw new InvitationUnavailable('An activated independent client is required.');
        }
        $raw = $this->tokens->next();
        $invitation = $this->transaction->run($recipient->id(), function () use ($recipient, $actorId, $firm, $raw): FirmLinkInvitation {
            if ($this->firm($actorId)->id() !== $firm->id()) {
                throw new InvitationUnavailable('The sender no longer belongs to this firm.');
            }
            $current = $this->independent($recipient->id());
            $previous = $this->invitations->forPair($current->id(), $firm->id());
            if (null !== $previous && null !== $previous->acceptedAt) {
                throw new InvitationUnavailable('Invitation was already accepted.');
            }
            $now = $this->clock->now();
            $invitation = new FirmLinkInvitation($previous->id ?? $this->ids->next(), $current->id(), $firm->id(), $current->email(), hash('sha256', $raw), $now, $now->modify('+24 hours'), $actorId);
            $this->invitations->save($invitation);

            return $invitation;
        });
        $this->audit->record(SecurityEvent::InvitationCreated, $actorId, $invitation->id);
        $this->notifier->send($invitation->email, $firm->name(), $raw);
    }

    public function inspect(string $actorId, string $raw): FirmLinkInvitation
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $raw)) {
            throw new InvitationUnavailable('Invalid invitation.');
        }
        $invitation = $this->invitations->findByHash(hash('sha256', $raw));
        if (null === $invitation || $invitation->ownerId !== $actorId || !$invitation->usable($this->clock->now()) || null === $this->firms->find($invitation->firmId)) {
            throw new InvitationUnavailable('Invitation is no longer available.');
        }
        $user = $this->independent($actorId);
        if ($user->email() !== $invitation->email) {
            throw new InvitationUnavailable('The recipient email has changed.');
        }

        return $invitation;
    }

    public function firmName(string $actorId, string $raw): string
    {
        return $this->firms->find($this->inspect($actorId, $raw)->firmId)?->name() ?? throw new InvitationUnavailable('Firm not available.');
    }

    public function accept(string $actorId, string $raw): void
    {
        $this->inspect($actorId, $raw);
        // All document/quarter operations use the same client lock. Account changes
        // additionally lock the user, preserving concurrent password/email changes.
        $this->transaction->run($actorId, function () use ($actorId, $raw): void {
            $this->accounts->forUser($actorId, function () use ($actorId, $raw): void {
                $invitation = $this->inspect($actorId, $raw);
                $user = $this->independent($actorId);
                $client = $this->clients->findByUserId($actorId) ?? throw new InvitationUnavailable('Client not available.');
                foreach ($this->documents->findByOwner($actorId) as $document) {
                    if (null !== $document->firmId()) {
                        throw new InvitationUnavailable('Inconsistent document ownership.');
                    }
                    $date = $document->issuedOn();
                    // Historical personal records retain their original fiscal scope.
                    if (null !== $date && $this->quarters->find($actorId, null, QuarterPeriod::forDate($date))?->closed) {
                        continue;
                    }
                    $this->documents->save($document->joinFirm($invitation->firmId));
                }
                $this->clients->save($client->joinFirm($invitation->firmId));
                $this->users->save($user->joinFirm($invitation->firmId));
                $this->invitations->save($invitation->accept($this->clock->now()));
            });
        });
        $this->audit->record(SecurityEvent::InvitationAccepted, $actorId);
    }

    private function independent(string $ownerId): User
    {
        $user = $this->users->find($ownerId);
        $client = $this->clients->findByUserId($ownerId);
        if (null === $user || !$user->isActivated() || Role::Client !== $user->role() || null !== $user->firmId() || null === $client || null !== $client->firmId()) {
            throw new InvitationUnavailable('An activated independent client is required.');
        }

        return $user;
    }

    private function firm(string $actorId): Firm
    {
        $actor = $this->users->find($actorId);
        if (null === $actor || !$actor->isActivated() || !in_array($actor->role(), [Role::FirmAdmin, Role::Advisor], true) || null === $actor->firmId()) {
            throw new InvitationUnavailable('Firm invitations are not available.');
        }

        return $this->firms->find($actor->firmId()) ?? throw new InvitationUnavailable('Firm not available.');
    }
}
