<?php

namespace App\Tests\Identidad;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Identidad\Infrastructure\Security\JwtIdentityEvents;
use App\Identidad\Infrastructure\Security\SecurityUser;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\TestCase;

final class AdministrativeIdentityTest extends TestCase
{
    private const ID = '00000000-0000-4000-8000-000000000001';
    private const FIRM = '00000000-0000-4000-8000-000000000002';

    public function testEmailChangePreservesIdentityAndRevokesPreviousSessionEvenAfterReturningToOldEmail(): void
    {
        $before = new User(self::ID, 'old@example.test', 'hash', Role::FirmAdmin, self::FIRM, true);
        $changed = $before->changeEmail(' NEW@Example.test ');
        self::assertSame($before->id(), $changed->id());
        self::assertSame($before->role(), $changed->role());
        self::assertSame($before->firmId(), $changed->firmId());
        self::assertSame($before->passwordHash(), $changed->passwordHash());
        self::assertTrue($changed->isActivated());
        self::assertSame('new@example.test', $changed->email());
        self::assertSame(2, $changed->authVersion());
        self::assertSame(2, $changed->withPassword('new-hash')->authVersion());
        $session = SecurityUser::fromDomain($before);
        self::assertTrue($session->isEqualTo(SecurityUser::fromDomain($before)));
        self::assertFalse($session->isEqualTo(SecurityUser::fromDomain($changed)));
        self::assertFalse($session->isEqualTo(SecurityUser::fromDomain($changed->changeEmail($before->email()))));
        self::assertFalse((new User(self::ID, 'pending@example.test', 'hash', Role::FirmAdmin, self::FIRM))->changeEmail('new@example.test')->isActivated());
    }

    public function testJwtCreationAlwaysBindsClaimsToActualAccount(): void
    {
        $user = new User(self::ID, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true, 4);
        $event = new JWTCreatedEvent(['uid' => 'injected', 'auth_version' => 1], SecurityUser::fromDomain($user));
        (new JwtIdentityEvents($this->createStub(UserRepository::class)))->created($event);
        self::assertSame(self::ID, $event->getData()['uid']);
        self::assertSame(4, $event->getData()['auth_version']);
    }

    public function testJwtRejectsOldVersionsAndReassignedEmailAddresses(): void
    {
        $user = new User(self::ID, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true, 3);
        $users = $this->createStub(UserRepository::class);
        $users->method('findByEmail')->willReturn($user);
        $listener = new JwtIdentityEvents($users);
        foreach ([[], ['uid' => self::ID, 'auth_version' => 2], ['uid' => self::FIRM, 'auth_version' => 3], ['uid' => self::ID, 'auth_version' => '3']] as $claims) {
            $event = new JWTDecodedEvent(['username' => $user->email()] + $claims);
            $listener->decoded($event);
            self::assertFalse($event->isValid());
        }
        $current = new JWTDecodedEvent(['username' => $user->email(), 'uid' => self::ID, 'auth_version' => 3]);
        $listener->decoded($current);
        self::assertTrue($current->isValid());
    }

    public function testLegacySerializedSessionsAreNotAccepted(): void
    {
        $user = SecurityUser::fromDomain(new User(self::ID, 'admin@example.test', 'hash', Role::FirmAdmin, self::FIRM, true));
        $serialized = serialize($user);
        $legacy = preg_replace('/s:\d+:"\x00[^";]+\x00authVersion";i:1;/', '', $serialized, -1, $removed);
        self::assertSame(1, $removed);
        $legacy = preg_replace_callback('/^(O:\d+:"[^"]+":)(\d+)(:\{)/', static fn (array $matches): string => $matches[1].((int) $matches[2] - 1).$matches[3], $legacy);
        $restored = unserialize($legacy, ['allowed_classes' => true]);
        self::assertInstanceOf(SecurityUser::class, $restored);
        self::assertFalse($restored->isEqualTo($user));
    }
}
