<?php

namespace App\Tests\Identidad;

use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Domain\ClientRepository;
use App\Identidad\Application\PlatformInvitations;
use App\Identidad\Domain\InvitationNotifier;
use App\Identidad\Domain\InvitationRepository;
use App\Identidad\Domain\InvitationTarget;
use App\Identidad\Domain\InvitationUnavailable;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\PlatformInvitation;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\FiscalContact;
use App\Shared\Domain\IdGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformInvitationsConcurrencyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function interveningChanges(): iterable
    {
        yield 'resent' => ['resent'];
        yield 'accepted' => ['accepted'];
        yield 'expired' => ['expired'];
    }

    #[DataProvider('interveningChanges')]
    public function testAcceptanceRechecksCurrentStateAfterLocking(string $change): void
    {
        $raw = str_repeat('a', 64);
        $now = new \DateTimeImmutable('2026-11-14 12:00:00 UTC');
        $invitation = new PlatformInvitation('00000000-0000-4000-8000-000000000001', 'synthetic@example.test', InvitationTarget::Advisory, hash('sha256', $raw), $now, $now->modify('+24 hours'));
        $current = match ($change) {
            'resent' => $invitation->resend(str_repeat('b', 64), $now),
            'accepted' => $invitation->accept($now),
            'expired' => new PlatformInvitation($invitation->id, $invitation->email, $invitation->target, $invitation->tokenHash, $now->modify('-25 hours'), $now->modify('-1 hour')),
            default => throw new \LogicException('Unknown test case.'),
        };
        $invitations = $this->createMock(InvitationRepository::class);
        $invitations->method('findByHash')->willReturn($invitation);
        $invitations->expects(self::once())->method('locked')->with($invitation->id, self::isCallable())->willReturnCallback(static function (string $id, callable $operation) use ($current): void { $operation($current); });
        $invitations->expects(self::never())->method('save');
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('save');
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($now);
        $passwords = $this->createStub(PasswordHasher::class);
        $passwords->method('hash')->willReturn('synthetic-hash');
        $service = new PlatformInvitations($invitations, $users, $this->createStub(FirmRepository::class), $this->createStub(ClientRepository::class), $this->createStub(InvitationNotifier::class), $this->createStub(TokenGenerator::class), $this->createStub(IdGenerator::class), $clock, $passwords);
        $this->expectException(InvitationUnavailable::class);
        $service->accept($raw, 'Ficticia', FiscalContact::required('00000000T', '600000000', 'Dirección ficticia', '28001', 'Municipio de ejemplo', 'Madrid'), 'synthetic-password');
    }
}
