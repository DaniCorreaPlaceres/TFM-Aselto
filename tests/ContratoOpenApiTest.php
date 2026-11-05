<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class ContratoOpenApiTest extends TestCase
{
    public function testElContratoExisteYDeclaraLaVersion(): void
    {
        $ruta = dirname(__DIR__).'/openapi/openapi.yaml';

        self::assertFileExists($ruta);
        self::assertStringContainsString('openapi: 3.0.3', (string) file_get_contents($ruta));
        self::assertStringContainsString('title: Aselto API', (string) file_get_contents($ruta));
    }
}
