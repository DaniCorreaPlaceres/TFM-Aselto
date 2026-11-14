<?php

namespace App\Tests\Identidad;

use App\Identidad\Application\ChoosePassword;
use App\Identidad\Application\IssueAccessToken;
use App\Identidad\Application\RequestPasswordReset;
use App\Identidad\Application\StartAccount;
use App\Identidad\Domain\AccessPurpose;
use App\Identidad\Domain\AccessToken;
use App\Identidad\Domain\AccessTokenRepository;
use App\Identidad\Domain\AccountNotifier;
use App\Identidad\Domain\AccountTransaction;
use App\Identidad\Domain\EmailAlreadyUsed;
use App\Identidad\Domain\InvalidAccessToken;
use App\Identidad\Domain\PasswordHasher;
use App\Identidad\Domain\PasswordTooShort;
use App\Identidad\Domain\Role;
use App\Identidad\Domain\TokenGenerator;
use App\Identidad\Domain\User;
use App\Identidad\Domain\UserRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\IdGenerator;
use App\Shared\Domain\Transaction;
use PHPUnit\Framework\TestCase;

final class AccountAccessTest extends TestCase
{
    public function testStartingAnAccountSendsAnActivationLink(): void
    {
        $users = new MemoryUserRepository();
        $notifier = new RecordingNotifier();
        $this->accounts($users, $notifier)->open('Ana@Example.test', Role::Client, null);

        $user = $users->findByEmail('ana@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isActivated());
        self::assertSame([['activation', 'ana@example.test']], $notifier->messages);
        self::assertSame(64, strlen($notifier->tokens[0]));
    }

    public function testChoosingAPasswordActivatesTheAccountOnce(): void
    {
        $users = new MemoryUserRepository();
        $tokens = new MemoryAccessTokenRepository();
        $notifier = new RecordingNotifier();
        $clock = new MutableClock('2026-11-07 00:00:00 UTC');
        $this->accounts($users, $notifier, $tokens, $clock)->open('ana@example.test', Role::Client, null);

        $this->passwords($users, $tokens, $clock)->execute($notifier->tokens[0], 'asesoria1', AccessPurpose::Activation);

        $user = $users->findByEmail('ana@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isActivated());
        self::assertSame('hash:asesoria1', $user->passwordHash());

        $this->expectException(InvalidAccessToken::class);
        $this->passwords($users, $tokens, $clock)->execute($notifier->tokens[0], 'asesoria2', AccessPurpose::Activation);
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $users = new MemoryUserRepository();
        $tokens = new MemoryAccessTokenRepository();
        $notifier = new RecordingNotifier();
        $clock = new MutableClock('2026-11-07 00:00:00 UTC');
        $this->accounts($users, $notifier, $tokens, $clock)->open('ana@example.test', Role::Client, null);
        $clock->move('+25 hours');

        $this->expectException(InvalidAccessToken::class);
        $this->passwords($users, $tokens, $clock)->execute($notifier->tokens[0], 'asesoria1', AccessPurpose::Activation);
    }

    public function testAShortPasswordIsRejected(): void
    {
        $users = new MemoryUserRepository();
        $tokens = new MemoryAccessTokenRepository();
        $notifier = new RecordingNotifier();
        $this->accounts($users, $notifier, $tokens)->open('ana@example.test', Role::Client, null);

        $this->expectException(PasswordTooShort::class);
        $this->passwords($users, $tokens)->execute($notifier->tokens[0], 'corta', AccessPurpose::Activation);
    }

    public function testARepeatedEmailIsRejected(): void
    {
        $users = new MemoryUserRepository();
        $users->save(new User(
            '00000000-0000-4000-8000-000000000099',
            'ana@example.test',
            'hash',
            Role::Client,
            null,
            true,
        ));

        $this->expectException(EmailAlreadyUsed::class);
        $this->accounts($users, new RecordingNotifier())->open('ana@example.test', Role::Advisor, '00000000-0000-4000-8000-000000000001');
    }

    public function testAnActivatedAccountCanRequestAReset(): void
    {
        $users = new MemoryUserRepository();
        $notifier = new RecordingNotifier();
        $users->save(new User(
            '00000000-0000-4000-8000-000000000099',
            'ana@example.test',
            'hash',
            Role::Client,
            null,
            true,
        ));

        $this->resets($users, $notifier)->execute('Ana@Example.test');

        self::assertSame([['reset', 'ana@example.test']], $notifier->messages);
    }

    public function testAnUnknownEmailSendsNothing(): void
    {
        $notifier = new RecordingNotifier();
        $this->resets(new MemoryUserRepository(), $notifier)->execute('nadie@example.test');

        self::assertSame([], $notifier->messages);
    }

    public function testAPendingAccountReceivesTheActivationLinkAgain(): void
    {
        $users = new MemoryUserRepository();
        $notifier = new RecordingNotifier();
        $this->accounts($users, $notifier)->open('ana@example.test', Role::Client, null);
        $notifier->messages = [];

        $this->resets($users, $notifier)->execute('ana@example.test');

        self::assertSame([['activation', 'ana@example.test']], $notifier->messages);
    }

    public function testAResetTokenCannotActivateAnAccount(): void
    {
        $users = new MemoryUserRepository();
        $tokens = new MemoryAccessTokenRepository();
        $notifier = new RecordingNotifier();
        $users->save(new User(
            '00000000-0000-4000-8000-000000000099',
            'ana@example.test',
            'hash',
            Role::Client,
            null,
            true,
        ));
        $this->resets($users, $notifier, $tokens)->execute('ana@example.test');

        $this->expectException(InvalidAccessToken::class);
        $this->passwords($users, $tokens)->execute($notifier->tokens[0], 'asesoria1', AccessPurpose::Activation);
    }

    private function accounts(
        UserRepository $users,
        RecordingNotifier $notifier,
        ?AccessTokenRepository $tokens = null,
        ?Clock $clock = null,
    ): StartAccount {
        $tokens ??= new MemoryAccessTokenRepository();
        $clock ??= new MutableClock('2026-11-07 00:00:00 UTC');

        return new StartAccount(
            $users,
            new FakePasswordHasher(),
            new FakeIdGenerator(),
            new SequenceTokenGenerator(),
            new IssueAccessToken($tokens, new FakeIdGenerator(), new SequenceTokenGenerator(), $clock),
            new ImmediateTransaction(),
            $notifier,
        );
    }

    private function passwords(UserRepository $users, AccessTokenRepository $tokens, ?Clock $clock = null): ChoosePassword
    {
        return new ChoosePassword($tokens, $users, new FakePasswordHasher(), $clock ?? new MutableClock('2026-11-07 00:00:00 UTC'), new ImmediateTransaction());
    }

    private function resets(
        UserRepository $users,
        RecordingNotifier $notifier,
        ?AccessTokenRepository $tokens = null,
    ): RequestPasswordReset {
        $tokens ??= new MemoryAccessTokenRepository();

        return new RequestPasswordReset(
            $users,
            new IssueAccessToken($tokens, new FakeIdGenerator(), new SequenceTokenGenerator(), new MutableClock('2026-11-07 00:00:00 UTC')),
            new ImmediateTransaction(),
            $notifier,
        );
    }
}

final class MemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function find(string $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id() === $id) {
                return $user;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->users[mb_strtolower(trim($email))] ?? null;
    }

    public function findByFirm(string $firmId): array
    {
        return [];
    }

    public function save(User $user): void
    {
        $this->users[$user->email()] = $user;
    }
}

final class MemoryAccessTokenRepository implements AccessTokenRepository
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function findByHash(string $tokenHash): ?AccessToken
    {
        return $this->tokens[$tokenHash] ?? null;
    }

    public function discardOpen(string $userId, AccessPurpose $purpose): void
    {
        foreach ($this->tokens as $hash => $token) {
            if ($token->userId() === $userId && $token->purpose() === $purpose && null === $token->usedAt()) {
                unset($this->tokens[$hash]);
            }
        }
    }

    public function save(AccessToken $token): void
    {
        $this->tokens[$token->tokenHash()] = $token;
    }
}

final class SequenceTokenGenerator implements TokenGenerator
{
    private int $sequence = 1;

    public function next(): string
    {
        return hash('sha256', 'token-'.$this->sequence++);
    }
}

final class MutableClock implements Clock
{
    public function __construct(private string $moment)
    {
    }

    public function move(string $modify): void
    {
        $this->moment = (new \DateTimeImmutable($this->moment))->modify($modify)->format('Y-m-d H:i:s').' UTC';
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->moment);
    }
}

final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hash:'.$plainPassword;
    }
}

final class FakeIdGenerator implements IdGenerator
{
    private int $sequence = 1;

    public function next(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $this->sequence++);
    }
}

final class ImmediateTransaction implements Transaction, AccountTransaction
{
    public function forUser(string $userId, callable $operation): void
    {
        $operation();
    }

    public function run(callable $operation): void
    {
        $operation();
    }
}

final class RecordingNotifier implements AccountNotifier
{
    /** @var list<array{0: string, 1: string}> */
    public array $messages = [];

    /** @var list<string> */
    public array $tokens = [];

    public function sendActivation(string $email, string $token): void
    {
        $this->messages[] = ['activation', $email];
        $this->tokens[] = $token;
    }

    public function sendPasswordReset(string $email, string $token): void
    {
        $this->messages[] = ['reset', $email];
        $this->tokens[] = $token;
    }
}
