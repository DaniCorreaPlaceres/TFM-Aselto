<?php

namespace App\Tests\Finanzas;

use App\Finanzas\Ui\ApiQuarterInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApiQuarterInputTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'broken JSON' => ['{'];
        yield 'array' => ['[]'];
        yield 'null' => ['null'];
        yield 'missing version' => ['{"fingerprint":"'.str_repeat('a', 64).'"}'];
        yield 'overflow version' => ['{"version":2147483648,"fingerprint":"'.str_repeat('a', 64).'"}'];
        yield 'nested value' => ['{"version":0,"fingerprint":{"value":"'.str_repeat('a', 64).'"}}'];
    }

    #[DataProvider('malformedBodies')]
    public function testMalformedCloseNeverReachesApplication(string $body): void
    {
        $request = Request::create('/api/v1/quarters/2026/4/close', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $this->expectException(BadRequestHttpException::class);
        new ApiQuarterInput()->mutation($request, true);
    }

    public function testFirstClosureAcceptsZeroVersion(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"version":0,"fingerprint":"'.str_repeat('a', 64).'"}');
        self::assertSame(['version' => 0, 'fingerprint' => str_repeat('a', 64)], new ApiQuarterInput()->mutation($request, true));
    }

    public function testReopeningCannotInjectFiguresOrScope(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"version":1,"scope":"personal"}');
        $this->expectException(BadRequestHttpException::class);
        new ApiQuarterInput()->mutation($request, false);
    }

    public function testLargeBodyIsRejected(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: str_repeat(' ', 4097));
        try {
            new ApiQuarterInput()->mutation($request, true);
            self::fail('Expected payload rejection.');
        } catch (HttpException $error) {
            self::assertSame(413, $error->getStatusCode());
        }
    }
}
