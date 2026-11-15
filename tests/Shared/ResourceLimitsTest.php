<?php

namespace App\Tests\Shared;

use App\Identidad\Domain\Role;
use App\Identidad\Domain\User;
use App\Identidad\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\RecordCriteria;
use App\Shared\Infrastructure\Http\ResourceLimits;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use Symfony\Component\Uid\Uuid;

final class ResourceLimitsTest extends TestCase
{
    public function testFirmBudgetCannotBeMultipliedByUsingOtherAccounts(): void
    {
        [$limits] = $this->limits();
        $firm = Uuid::v4()->toRfc4122();
        foreach ([self::actor($firm), self::actor($firm)] as $actor) {
            for ($i = 0; $i < 6; ++$i) {
                self::assertSame(0, $limits->consume($actor, 'upload'));
            }
        }
        self::assertGreaterThan(0, $limits->consume(self::actor($firm), 'upload'));
        self::assertSame(0, $limits->consume(self::actor(Uuid::v4()->toRfc4122()), 'upload'));
        self::assertSame(0, $limits->consume(self::actor(null), 'upload'));
    }

    public function testBudgetsExpireAndDoNotBlockOtherOperations(): void
    {
        [$limits, $storage] = $this->limits();
        $actor = self::actor(null);
        for ($i = 0; $i < 10; ++$i) {
            self::assertSame(0, $limits->consume($actor, 'upload'));
        }
        self::assertGreaterThan(0, $limits->consume($actor, 'upload'));
        self::assertSame(0, $limits->consume($actor, 'read'));
        self::assertSame(0, $limits->consume($actor, 'export'));
        foreach ($storage->states as $id => $state) {
            self::assertMatchesRegularExpression('/^[a-z-]+-[a-f0-9]{64}$/', $id);
            (new \ReflectionProperty($state, 'windowEndAt'))->setValue($state, microtime(true) - 3600);
        }
        self::assertSame(0, $limits->consume($actor, 'upload'));
    }

    public function testListingRejectsUnboundedOrHostileCriteria(): void
    {
        foreach ([['limit' => -1], ['limit' => 101], ['offset' => -1], ['offset' => 1_000_001], ['column' => 21], ['order' => 'asc; DROP TABLE identity_user'], ['search' => str_repeat('a', 121)], ['owner' => 'not-an-owner']] as $arguments) {
            try {
                new RecordCriteria(...$arguments);
                self::fail('Invalid listing criteria accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @return array{ResourceLimits, ResourceTestStorage} */
    private function limits(): array
    {
        $storage = new ResourceTestStorage();
        $factory = static fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory(['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'], $storage);

        return [new ResourceLimits($factory('upload-actor', 10), $factory('upload-firm', 12), $factory('read-actor', 180), $factory('read-firm', 1800), $factory('export-actor', 3), $factory('export-firm', 30), $factory('write-actor', 60), $factory('write-firm', 600), 'synthetic-secret'), $storage];
    }

    private static function actor(?string $firm): SecurityUser
    {
        return SecurityUser::fromDomain(new User(Uuid::v4()->toRfc4122(), 'synthetic@example.test', 'unused-hash', Role::Client, $firm, true));
    }
}

final class ResourceTestStorage implements StorageInterface
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
