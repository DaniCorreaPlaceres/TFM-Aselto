<?php

namespace App\Tests\Shared;

use App\Shared\Domain\InvalidFiscalContact;
use App\Shared\Domain\SpanishTaxId;
use PHPUnit\Framework\TestCase;

final class SpanishTaxIdTest extends TestCase
{
    public function testANifNieAndCifAreAccepted(): void
    {
        self::assertSame('00000000T', SpanishTaxId::normalize('00000000t'));
        self::assertSame('X1234567L', SpanishTaxId::normalize('X-1234567-L'));
        self::assertSame('B12345674', SpanishTaxId::normalize('B12345674'));
        self::assertSame('P1234567D', SpanishTaxId::normalize('P1234567D'));
    }

    public function testAWrongControlCharacterIsRejected(): void
    {
        $this->expectException(InvalidFiscalContact::class);

        SpanishTaxId::normalize('00000000A');
    }
}
