<?php

namespace App\Shared\Infrastructure;

use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RecordExport
{
    /** @param list<string> $headers
     * @param iterable<list<string|float>> $rows
     */
    public function write(string $format, array $headers, iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aselto-export-');
        if (false === $path) {
            throw new \RuntimeException('Cannot create private export.');
        }
        chmod($path, 0600);
        $started = microtime(true);
        $writer = null;
        $stream = null;
        $scratch = $path.'-work';
        try {
            if ('xlsx' === $format) {
                if (!mkdir($scratch, 0700)) {
                    throw new \RuntimeException('Cannot create private workbook workspace.');
                }
                $options = new Options();
                $options->setTempFolder($scratch);
                $writer = new Writer($options);
                $writer->openToFile($path);
                $writer->addRow(new Row(array_map(static fn (string $value): StringCell => new StringCell($value, null), $headers)));
            } else {
                $stream = fopen($path, 'wb');
                if (false === $stream) {
                    throw new \RuntimeException('Cannot open private export.');
                }
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, $headers, ';', '"', '');
            }
            foreach ($rows as $values) {
                if (microtime(true) - $started > 30) {
                    throw new HttpException(413, 'La exportación es demasiado grande. Acota la búsqueda e inténtalo de nuevo.');
                }
                if (null !== $writer) {
                    // Never let OpenSpout infer FormulaCell from an untrusted string.
                    $writer->addRow(new Row(array_map(static fn (string|float $value): StringCell|NumericCell => is_float($value) ? new NumericCell($value, null) : new StringCell($value, null), $values)));
                } elseif (is_resource($stream)) {
                    fputcsv($stream, array_map(self::csv(...), $values), ';', '"', '');
                }
            }
            $writer?->close();
            if (is_resource($stream)) {
                fclose($stream);
            }
            chmod($path, 0600);

            return $path;
        } catch (\Throwable $exception) {
            try {
                $writer?->close();
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $exception;
        } finally {
            if (is_dir($scratch)) {
                (new Filesystem())->remove($scratch);
            }
        }
    }

    public static function csv(string|float $value): string
    {
        if (is_float($value)) {
            return number_format($value, 2, ',', '');
        }

        return preg_match('/^[\s\x00-\x20]*[=+\-@\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
