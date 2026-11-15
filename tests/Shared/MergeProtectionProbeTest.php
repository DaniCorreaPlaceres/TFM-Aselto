<?php

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

final class MergeProtectionProbeTest extends TestCase
{
    public function testRequiredCheckBlocksMerge(): void
    {
        self::fail('Intentional merge-protection probe; close without merging.');
    }
}
