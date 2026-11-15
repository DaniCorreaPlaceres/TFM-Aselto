<?php

namespace App\Tests\Identidad;

use App\Identidad\Infrastructure\Security\AuthenticationRateLimits;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class AuthenticationRateLimitsTest extends TestCase
{
    public function testLoginSharesNormalizedIdentityButDoesNotGloballyLockAnAccount(): void
    {
        [$limits] = $this->limits();
        for ($i = 0; $i < 5; ++$i) {
            self::assertSame(0, $limits->login('192.0.2.1', ' User@Example.test '));
        }
        self::assertGreaterThan(0, $limits->login('192.0.2.1', 'user@example.test'));
        self::assertGreaterThan(0, $limits->login('192.0.2.1', 'usér@example.test'));
        self::assertSame(0, $limits->login('192.0.2.2', 'user@example.test'));
        self::assertSame(0, $limits->login('192.0.2.1', 'other@example.test'));
    }

    public function testChangingIdentityCannotBypassTheIpBudget(): void
    {
        [$limits] = $this->limits();
        for ($i = 0; $i < 60; ++$i) {
            self::assertSame(0, $limits->login('192.0.2.1', $i.'@example.test'));
        }
        self::assertGreaterThan(0, $limits->login('192.0.2.1', 'new@example.test'));
        self::assertSame(0, $limits->login('192.0.2.2', 'new@example.test'));
    }

    public function testRecoveryLimitsByEmailAcrossIpsAndByIpAcrossEmails(): void
    {
        [$limits] = $this->limits();
        for ($i = 1; $i <= 3; ++$i) {
            self::assertSame(0, $limits->recovery('192.0.2.'.$i, ' USER@example.test '));
        }
        self::assertGreaterThan(0, $limits->recovery('192.0.2.4', 'user@example.test'));
        self::assertSame(0, $limits->login('192.0.2.4', 'user@example.test'));
        for ($i = 0; $i < 10; ++$i) {
            self::assertSame(0, $limits->recovery('192.0.2.10', $i.'@example.test'));
        }
        self::assertGreaterThan(0, $limits->recovery('192.0.2.10', 'new@example.test'));
    }

    public function testWindowsRecoverWithoutWaitingAndKeysContainNoPlainIdentifiers(): void
    {
        [$limits, $storage] = $this->limits();
        for ($i = 0; $i < 3; ++$i) {
            $limits->recovery('192.0.2.1', 'synthetic@example.test');
        }
        self::assertGreaterThan(0, $limits->recovery('192.0.2.1', 'synthetic@example.test'));
        foreach ($storage->states as $id => $state) {
            self::assertMatchesRegularExpression('/^(login-ip|login-identity|recovery-ip|recovery-identity)-[a-f0-9]{64}$/', $id);
            // Simulate both windows being in the past; exercise the real policy's expiry path.
            (new \ReflectionProperty($state, 'windowEndAt'))->setValue($state, microtime(true) - 3600);
        }
        self::assertSame(0, $limits->recovery('192.0.2.1', 'synthetic@example.test'));
    }

    /** @return array{AuthenticationRateLimits, AuthenticationLimitTestStorage} */
    private function limits(): array
    {
        $storage = new AuthenticationLimitTestStorage();
        $factory = static fn (string $id, int $limit, string $interval): RateLimiterFactory => new RateLimiterFactory(['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval], $storage);

        return [new AuthenticationRateLimits($factory('login-ip', 60, '1 minute'), $factory('login-identity', 5, '1 minute'), $factory('recovery-ip', 10, '15 minutes'), $factory('recovery-identity', 3, '15 minutes'), 'synthetic-test-secret'), $storage];
    }
}

final class AuthenticationLimitTestStorage implements StorageInterface
{
    /** @var array<string, LimiterStateInterface> */
    public array $states = [];

    public function save(LimiterStateInterface $limiterState): void
    {
        $this->states[$limiterState->getId()] = $limiterState;
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        return $this->states[$limiterStateId] ?? null;
    }

    public function delete(string $limiterStateId): void
    {
        unset($this->states[$limiterStateId]);
    }
}
