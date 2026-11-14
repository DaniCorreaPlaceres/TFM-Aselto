<?php

namespace App\Tests\Shared;

use App\Shared\Domain\DocumentQuotaExceeded;
use App\Shared\Domain\LicensePlan;
use App\Shared\Domain\MonthlyQuota;
use PHPUnit\Framework\TestCase;

final class MonthlyQuotaTest extends TestCase
{
    public function testCanarySummerBoundaryAndWinterMonth(): void
    {
        self::assertSame('2026-08', MonthlyQuota::month(new \DateTimeImmutable('2026-07-31 23:00:00 UTC')));
        self::assertSame('2026-07', MonthlyQuota::month(new \DateTimeImmutable('2026-07-31 22:59:59 UTC')));
        self::assertSame('2026-12', MonthlyQuota::month(new \DateTimeImmutable('2026-12-01 00:00:00 UTC')));
    }

    public function testTwentyIsTheFreeBoundaryAndPaidPlansRetainUsage(): void
    {
        (new MonthlyQuota(LicensePlan::Free, '2026-11', 19))->assertAvailable();
        foreach ([LicensePlan::Professional, LicensePlan::Advisory] as $plan) {
            $quota = new MonthlyQuota($plan, '2026-11', 120);
            $quota->assertAvailable();
            self::assertNull($quota->limit());
            self::assertSame(120, $quota->used);
        }
        $this->expectException(DocumentQuotaExceeded::class);
        (new MonthlyQuota(LicensePlan::Free, '2026-11', 20))->assertAvailable();
    }
}
