<?php

namespace App\Tests\Identidad;

use App\Identidad\Application\ChoosePassword;
use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Application\RequestPasswordReset;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\InvalidAccessToken;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use PHPUnit\Framework\TestCase;

final class PasswordEmailConcurrencyTest extends TestCase
{
    private const USER = '00000000-0000-4000-8000-000000000001';

    public function testResetCannotRestoreAnEmailAfterItsTokenWasInvalidatedWhileWaitingForLock(): void
    {
        $raw = str_repeat('a', 64);
        $now = new \DateTimeImmutable('2026-11-14 12:00:00 UTC');
        $token = new AccessToken('00000000-0000-4000-8000-000000000002', self::USER, hash('sha256', $raw), AccessPurpose::Reset, $now->modify('+2 hours'), null);
        $tokens = $this->createMock(AccessTokenRepository::class);
        $tokens->expects(self::exactly(2))->method('findByHash')->willReturnOnConsecutiveCalls($token, null);
        $tokens->expects(self::never())->method('save');
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::never())->method('save');
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($now);
        $transaction = $this->createMock(AccountTransaction::class);
        $transaction->expects(self::once())->method('forUser')->with(self::USER, self::isCallable())->willReturnCallback(static function (string $id, callable $operation): void { $operation(); });
        $service = new ChoosePassword($tokens, $users, $this->createStub(PasswordHasher::class), $clock, $transaction);
        $this->expectException(InvalidAccessToken::class);
        $service->execute($raw, 'synthetic-password', AccessPurpose::Reset);
    }

    public function testResetRequestCannotSendANewLinkToOldEmailAfterWaitingForLock(): void
    {
        $before = new User(self::USER, 'old@example.test', 'hash', Role::Client, null, true);
        $users = $this->createStub(UserRepository::class);
        $users->method('findByEmail')->willReturn($before);
        $users->method('find')->willReturn($before->changeEmail('new@example.test'));
        $tokens = $this->createMock(AccessTokenRepository::class);
        $tokens->expects(self::never())->method('save');
        $tokens->expects(self::never())->method('discardOpen');
        $notifier = $this->createMock(AccountNotifier::class);
        $notifier->expects(self::never())->method('sendPasswordReset');
        $notifier->expects(self::never())->method('sendActivation');
        $transaction = $this->createMock(AccountTransaction::class);
        $transaction->expects(self::once())->method('forUser')->with(self::USER, self::isCallable())->willReturnCallback(static function (string $id, callable $operation): void { $operation(); });
        $issuer = new IssueAccessToken($tokens, $this->createStub(IdGenerator::class), $this->createStub(TokenGenerator::class), $this->createStub(Clock::class));
        (new RequestPasswordReset($users, $issuer, $transaction, $notifier))->execute('old@example.test');
    }
}
