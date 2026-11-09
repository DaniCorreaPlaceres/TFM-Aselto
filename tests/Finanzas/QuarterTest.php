<?php

namespace App\Tests\Finanzas;

use App\Finanzas\Domain\FinancialRecord;
use App\Finanzas\Domain\InvalidQuarter;
use App\Finanzas\Domain\Quarter;
use App\Finanzas\Domain\QuarterCalculation;
use App\Finanzas\Domain\QuarterChanged;
use App\Finanzas\Domain\QuarterMovement;
use App\Finanzas\Domain\QuarterPeriod;
use App\Finanzas\Domain\QuarterSummary;
use App\Finanzas\Infrastructure\Persistence\DoctrineQuarter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuarterTest extends TestCase
{
    /** @return iterable<string, array{string, int}> */
    public static function boundaries(): iterable
    {
        yield 'January' => ['2026-01-01', 1];
        yield 'March' => ['2026-03-31', 1];
        yield 'April' => ['2026-04-01', 2];
        yield 'June' => ['2026-06-30', 2];
        yield 'July' => ['2026-07-01', 3];
        yield 'September' => ['2026-09-30', 3];
        yield 'October' => ['2026-10-01', 4];
        yield 'December' => ['2026-12-31', 4];
    }

    #[DataProvider('boundaries')]
    public function testFiscalDateDeterminesQuarter(string $date, int $number): void
    {
        $date = new \DateTimeImmutable($date.' 00:00:00 +14:00');
        $period = QuarterPeriod::forDate($date);
        self::assertSame(2026, $period->year);
        self::assertSame($number, $period->number);
        self::assertTrue($period->contains($date));
        self::assertFalse($period->contains($date->modify('+1 year')));
    }

    public function testInvalidQuarterIsRejected(): void
    {
        $this->expectException(InvalidQuarter::class);
        new QuarterPeriod(2026, 5);
    }

    public function testLastSupportedYearContainsDecember(): void
    {
        self::assertTrue((new QuarterPeriod(9999, 4))->contains(new \DateTimeImmutable('9999-12-31')));
    }

    public function testTotalsKeepTaxesSurchargeAndWithholdingSeparate(): void
    {
        $date = new \DateTimeImmutable('2026-10-01');
        $summary = new QuarterSummary([
            new QuarterMovement('a', $date, true, 'Ingreso IVA', 10000, 'iva', 2100, 0, 1500, 10600),
            new QuarterMovement('b', $date, false, 'Gasto IVA', 2000, 'iva', 420, 104, 0, 2524),
            new QuarterMovement('c', $date, true, 'Ingreso IGIC', 3000, 'igic', 210, 0, 0, 3210),
            new QuarterMovement('d', $date, false, 'Gasto IPSI', 500, 'ipsi', 20, 0, 0, 520),
            new QuarterMovement('e', $date, false, 'Cero', 0, 'iva', 0, 0, 0, 0),
        ]);
        self::assertSame(13000, $summary->incomeCents());
        self::assertSame(2500, $summary->expenseCents());
        self::assertSame(10500, $summary->resultCents());
        self::assertSame(['output' => 2100, 'input' => 420, 'balance' => 1680], $summary->taxes()['iva']);
        self::assertSame(210, $summary->taxes()['igic']['balance']);
        self::assertSame(-20, $summary->taxes()['ipsi']['balance']);
        self::assertSame(104, $summary->surchargeCents());
        self::assertSame(1500, $summary->withholdingCents());
    }

    public function testOnlyPeriodPendingAndAllUndatedRecordsBlockClosing(): void
    {
        $records = [self::record('included', '2026-10-01', true), self::record('pending', '2026-12-31', false), self::record('other', '2027-01-01', false), self::record('undated', null, false)];
        $calculation = new QuarterCalculation('owner', null, new QuarterPeriod(2026, 4), $records);
        self::assertSame(['pending', 'undated'], array_column($calculation->pending, 'id'));
        self::assertSame(10000, $calculation->summary->incomeCents());
        self::assertSame($calculation->fingerprint, (new QuarterCalculation('owner', null, new QuarterPeriod(2026, 4), array_reverse($records)))->fingerprint);
        self::assertNotSame($calculation->fingerprint, (new QuarterCalculation('owner', null, new QuarterPeriod(2026, 4), [self::record('included', '2026-10-01', true)]))->fingerprint);
    }

    public function testIncompleteIncludedRecordIsNotSilentlyConvertedToZero(): void
    {
        $this->expectException(InvalidQuarter::class);
        (new FinancialRecord('id', new \DateTimeImmutable('2026-11-01'), 'submitted', true, true, 'Ejemplo', null, 'iva', 0, 0, 0, null, 1))->movement();
    }

    public function testReopeningAndReclosingPreserveOldSnapshotsAndActors(): void
    {
        $at = new \DateTimeImmutable('2026-11-09 13:00 UTC');
        $first = new QuarterSummary([self::record('a', '2026-10-01', true)->movement()]);
        $quarter = Quarter::firstClose('owner', null, new QuarterPeriod(2026, 4), $first, 'owner', $at);
        $quarter = $quarter->reopen('owner', $at->modify('+1 hour'))->close(new QuarterSummary([]), 'owner', $at->modify('+2 hours'));
        self::assertCount(3, $quarter->history);
        self::assertTrue($quarter->closed);
        self::assertSame(0, $quarter->snapshot->incomeCents());
        self::assertSame(10000, $quarter->history[0]->snapshot->incomeCents());
        self::assertSame(10000, $quarter->history[1]->snapshot->incomeCents());
        self::assertSame('owner', $quarter->history[1]->actorId);
        $entity = new DoctrineQuarter();
        $entity->apply($quarter);
        self::assertEquals($quarter, $entity->toDomain());
    }

    public function testDuplicateCloseIsRejected(): void
    {
        $quarter = Quarter::firstClose('owner', null, new QuarterPeriod(2026, 4), new QuarterSummary([]), 'owner', new \DateTimeImmutable());
        $this->expectException(QuarterChanged::class);
        $quarter->close(new QuarterSummary([]), 'owner', new \DateTimeImmutable());
    }

    private static function record(string $id, ?string $date, bool $included): FinancialRecord
    {
        return new FinancialRecord($id, null === $date ? null : new \DateTimeImmutable($date), $included ? 'submitted' : 'draft', $included, true, 'Ejemplo', 10000, 'iva', 2100, 0, 0, 12100, 1);
    }
}
