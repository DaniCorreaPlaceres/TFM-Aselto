<?php

namespace App\Tests\Identidad;

use App\Identidad\Domain\InvalidUser;
use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\PlatformInvitation;
use App\Identidad\Infrastructure\Persistence\DoctrineInvitation;
use PHPUnit\Framework\TestCase;

final class PlatformInvitationTest extends TestCase
{
    public function testEmailIsNormalizedAndExpiryIsExclusive(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00 UTC');
        $invitation = $this->invitation($now);
        self::assertSame('invite@example.test', $invitation->email);
        self::assertTrue($invitation->isUsable($now));
        self::assertFalse($invitation->isUsable($now->modify('+24 hours')));
    }

    public function testResendRenewsExpiredInvitationsWithADifferentHash(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00 UTC');
        $invitation = $this->invitation($now);
        $resend = $invitation->resend(str_repeat('b', 64), $now->modify('+25 hours'));
        self::assertSame($invitation->id, $resend->id);
        self::assertSame($invitation->email, $resend->email);
        self::assertSame($invitation->target, $resend->target);
        self::assertNotSame($invitation->tokenHash, $resend->tokenHash);
        self::assertTrue($resend->isUsable($now->modify('+48 hours')));
    }

    public function testAcceptanceConsumesTheInvitation(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00 UTC');
        $accepted = $this->invitation($now)->accept($now);
        self::assertFalse($accepted->isUsable($now));
        $this->expectException(InvitationUnavailable::class);
        $accepted->accept($now);
    }

    public function testAcceptedInvitationsCannotBeResent(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00 UTC');
        $this->expectException(InvitationUnavailable::class);
        $this->invitation($now)->accept($now)->resend(str_repeat('b', 64), $now);
    }

    public function testExpiredInvitationsCannotBeAccepted(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00 UTC');
        $this->expectException(InvitationUnavailable::class);
        $this->invitation($now)->accept($now->modify('+24 hours'));
    }

    public function testInvalidEmailsAreRejected(): void
    {
        $this->expectException(InvalidUser::class);
        new PlatformInvitation('00000000-0000-4000-8000-000000000001', 'invalid', InvitationTarget::Advisory, str_repeat('a', 64), new \DateTimeImmutable(), new \DateTimeImmutable('+24 hours'));
    }

    public function testHydrationKeepsUtcExpiryWhenPhpUsesAnotherTimezone(): void
    {
        $now = new \DateTimeImmutable('2026-11-14 10:00:00 UTC');
        $invitation = $this->invitation($now)->accept($now);
        $record = new DoctrineInvitation();
        $record->apply($invitation);
        foreach (['sentAt' => $invitation->sentAt, 'expiresAt' => $invitation->expiresAt, 'acceptedAt' => $invitation->acceptedAt] as $field => $date) {
            (new \ReflectionProperty($record, $field))->setValue($record, new \DateTimeImmutable($date->format('Y-m-d H:i:s'), new \DateTimeZone('Europe/Madrid')));
        }
        $restored = $record->toDomain();
        self::assertSame($invitation->expiresAt->getTimestamp(), $restored->expiresAt->getTimestamp());
        self::assertSame($invitation->sentAt->getTimestamp(), $restored->sentAt->getTimestamp());
        self::assertSame($invitation->acceptedAt->getTimestamp(), $restored->acceptedAt?->getTimestamp());
    }

    private function invitation(\DateTimeImmutable $now): PlatformInvitation
    {
        return new PlatformInvitation('00000000-0000-4000-8000-000000000001', ' Invite@Example.test ', InvitationTarget::Advisory, str_repeat('a', 64), $now, $now->modify('+24 hours'));
    }
}
