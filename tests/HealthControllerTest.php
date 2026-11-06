<?php

namespace App\Tests;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends TestCase
{
    public function testReturnsOkWhenTheDatabaseResponds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->with('SELECT 1')->willReturn('1');

        $response = (new HealthController($connection))();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['estado' => 'ok', 'base_de_datos' => 'ok'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testReturnsErrorWhenTheDatabaseDoesNotRespond(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willThrowException(new InvalidArgumentException('cannot connect'));

        $response = (new HealthController($connection))();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(
            ['estado' => 'error', 'base_de_datos' => 'error'],
            json_decode((string) $response->getContent(), true),
        );
    }
}
