<?php

namespace App\Tests\Identidad;

use App\Identidad\Application\SessionLifetime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionLifetimeTest extends TestCase
{
    public function testActivityRenewsOnlyIdleAndCannotBypassTheAbsoluteLimit(): void
    {
        $policy = new SessionLifetime(30, 80);
        $state = $policy->start(100);
        self::assertFalse($policy->expired($state, 129));
        self::assertTrue($policy->expired($state, 130));
        foreach ([125, 150, 175] as $now) {
            $state = $policy->activity($state, $now);
        }
        self::assertSame(100, $state['started_at']);
        self::assertSame(175, $state['last_activity_at']);
        self::assertFalse($policy->expired($state, 179));
        self::assertTrue($policy->expired($state, 180));
        $this->expectException(\LogicException::class);
        $policy->activity($state, 180);
    }

    #[DataProvider('invalidMetadata')]
    public function testLegacyOrInvalidMetadataRequiresReauthentication(mixed $state): void
    {
        self::assertTrue((new SessionLifetime())->expired($state, 100));
    }

    /** @return iterable<array{mixed}> */
    public static function invalidMetadata(): iterable
    {
        yield [null];
        yield [[]];
        yield ['tampered'];
        yield [['started_at' => 1]];
        yield [['started_at' => '1', 'last_activity_at' => 1]];
        yield [['started_at' => 1, 'last_activity_at' => '1']];
        yield [['started_at' => -1, 'last_activity_at' => 1]];
        yield [['started_at' => 50, 'last_activity_at' => 40]];
        yield [['started_at' => 101, 'last_activity_at' => 101]];
        yield [['started_at' => 1, 'last_activity_at' => 101]];
    }

    public function testDefaultsHaveExactThirtyMinuteAndEightHourBoundaries(): void
    {
        $policy = new SessionLifetime();
        $state = $policy->start(1000);
        self::assertFalse($policy->expired($state, 2799));
        self::assertTrue($policy->expired($state, 2800));
        $state['last_activity_at'] = 29799;
        self::assertFalse($policy->expired($state, 29799));
        self::assertTrue($policy->expired($state, 29800));
    }

    #[DataProvider('invalidConfiguration')]
    public function testRejectsInvalidConfiguration(int $idle, int $absolute): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SessionLifetime($idle, $absolute);
    }

    /** @return iterable<array{int, int}> */
    public static function invalidConfiguration(): iterable
    {
        yield [0, 80];
        yield [-1, 80];
        yield [30, 0];
        yield [80, 30];
    }
}
