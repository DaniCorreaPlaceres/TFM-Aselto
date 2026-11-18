<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class ModuleStructureTest extends TestCase
{
    public function testEachModuleHasTheFourLayers(): void
    {
        $modules = ['Shared', 'Identidad', 'Asesoria', 'Cliente', 'Documento', 'Finanzas', 'Comunicacion'];
        $layers = ['Domain', 'Application', 'Infrastructure', 'Ui'];

        foreach ($modules as $module) {
            foreach ($layers as $layer) {
                self::assertDirectoryExists(dirname(__DIR__).'/src/'.$module.'/'.$layer);
            }
        }
    }
}
