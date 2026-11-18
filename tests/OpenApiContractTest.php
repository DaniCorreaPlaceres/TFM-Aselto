<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class OpenApiContractTest extends TestCase
{
    public function testContractExistsAndDeclaresTheVersion(): void
    {
        $path = dirname(__DIR__).'/openapi/openapi.yaml';

        self::assertFileExists($path);
        self::assertStringContainsString('openapi: 3.0.3', (string) file_get_contents($path));
        self::assertStringContainsString('title: Aselto API', (string) file_get_contents($path));
    }
}
