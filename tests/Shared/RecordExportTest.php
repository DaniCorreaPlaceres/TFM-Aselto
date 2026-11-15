<?php

namespace App\Tests\Shared;

use App\Shared\Infrastructure\RecordExport;
use PHPUnit\Framework\TestCase;

final class RecordExportTest extends TestCase
{
    public function testExcelStoresFormulaLikeNamesAsTextAndAmountsAsNumbers(): void
    {
        $path = (new RecordExport())->write('xlsx', ['Nombre', 'Importe'], [['=SUM(A1:A2)', -12.34], ['@command', 0.0]]);
        try {
            self::assertSame(0600, fileperms($path) & 0777);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            self::assertIsString($xml);
            self::assertStringNotContainsString('<f>', $xml);
            self::assertStringContainsString('=SUM(A1:A2)', $xml);
            self::assertStringContainsString('t="inlineStr"', $xml);
            self::assertStringContainsString('<v>-12.34</v>', $xml);
        } finally {
            unlink($path);
        }
    }

    public function testCsvNeutralizesFormulasButPreservesNegativeAmounts(): void
    {
        self::assertSame("'=SUM(A1)", RecordExport::csv('=SUM(A1)'));
        self::assertSame("' \t@command", RecordExport::csv(" \t@command"));
        self::assertSame('-12,34', RecordExport::csv(-12.34));
        $path = (new RecordExport())->write('csv', ['Nombre', 'Importe'], [['=SUM(A1)', -12.34]]);
        try {
            $data = file_get_contents($path);
            self::assertStringStartsWith("\xEF\xBB\xBF", $data);
            self::assertStringContainsString("'=SUM(A1);-12,34", $data);
        } finally {
            unlink($path);
        }
    }

    public function testFailedProducerRemovesItsIncompletePrivateExport(): void
    {
        $before = glob(sys_get_temp_dir().'/aselto-export-*');
        $rows = static function (): \Generator {
            yield ['First row'];
            throw new \RuntimeException('Synthetic export failure.');
        };
        try {
            (new RecordExport())->write('xlsx', ['Header'], $rows());
            self::fail('Expected producer failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic export failure.', $exception->getMessage());
        }
        self::assertSame($before, glob(sys_get_temp_dir().'/aselto-export-*'));
    }
}
