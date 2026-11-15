<?php

namespace App\Tests\Identidad;

use App\Identidad\Application\PasswordPolicy;
use App\Identidad\Domain\PasswordRejected;
use App\Identidad\Domain\PasswordTooShort;
use App\Identidad\Infrastructure\Security\LocalCommonPasswords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

final class PasswordPolicyTest extends TestCase
{
    #[DataProvider('acceptedPasswords')]
    public function testAcceptsLongPhrasesWithoutCompositionRules(string $password): void
    {
        $this->policy()->assertAcceptable($password);
        self::assertGreaterThanOrEqual(15, mb_strlen($password, 'UTF-8'));
    }

    /** @return iterable<array{string}> */
    public static function acceptedPasswords(): iterable
    {
        yield ['una frase larga']; // Exactly 15 code points; no symbols required.
        yield ['  una frase con espacios  '];
        yield [str_repeat('ñ', 128)];
        yield [str_repeat('🪴', 15)];
    }

    public function testRejectsFourteenCharacters(): void
    {
        $this->expectException(PasswordTooShort::class);
        $this->policy()->assertAcceptable(str_repeat('ñ', 14));
    }

    #[DataProvider('rejectedPasswords')]
    public function testRejectsCommonLongAndUnreadablePasswords(string $password, string $reason): void
    {
        try {
            $this->policy()->assertAcceptable($password);
            self::fail('Expected a password rejection.');
        } catch (PasswordRejected $error) {
            self::assertSame($reason, $error->reason);
            self::assertStringNotContainsString($password, $error->getMessage());
        }
    }

    /** @return iterable<array{string, string}> */
    public static function rejectedPasswords(): iterable
    {
        yield ['films+pic+galeries', PasswordRejected::COMMON];
        yield [' FILMS+PIC+GALERIES ', PasswordRejected::COMMON];
        yield ['123456789012345', PasswordRejected::COMMON];
        yield ['passwordpassword', PasswordRejected::COMMON];
        yield ['correct horse battery staple', PasswordRejected::COMMON];
        yield [str_repeat(' ', 15), PasswordRejected::COMMON];
        yield [str_repeat('ñ', 129), PasswordRejected::TOO_LONG];
        yield [str_repeat('a', 513), PasswordRejected::TOO_LONG];
        yield ["unreadable text\xff", PasswordRejected::INVALID_ENCODING];
    }

    public function testListHasPinnedBytesAndAllImportedEntriesAreBlocked(): void
    {
        $file = dirname(__DIR__, 2).'/resources/security/common-passwords.txt';
        self::assertSame(LocalCommonPasswords::SHA256, hash_file('sha256', $file));
        $entries = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertCount(10001, $entries);
        $list = new LocalCommonPasswords($file);
        self::assertSame([], array_values(array_filter($entries, static fn (string $entry): bool => !$list->contains(mb_strtolower(trim($entry), 'UTF-8')))));
    }

    public function testMissingListDoesNotSilentlyAcceptPasswords(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LocalCommonPasswords(__DIR__.'/missing-password-list.txt'))->contains('synthetic');
    }

    public function testDamagedListDoesNotSilentlyAcceptPasswords(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'aselto-password-');
        try {
            file_put_contents($file, 'damaged synthetic list');
            $this->expectException(\RuntimeException::class);
            (new LocalCommonPasswords($file))->contains('synthetic');
        } finally {
            unlink($file);
        }
    }

    public function testNativeHasherPreservesLongUnicodeAndWhitespaceWithoutTruncating(): void
    {
        $password = '  '.str_repeat('ñ', 60).' alfa  ';
        $this->policy()->assertAcceptable($password);
        $hasher = new NativePasswordHasher(cost: 4);
        $hash = $hasher->hash($password);
        self::assertTrue($hasher->verify($hash, $password));
        self::assertFalse($hasher->verify($hash, trim($password)));
        self::assertFalse($hasher->verify($hash, '  '.str_repeat('ñ', 60).' beta  '));
    }

    private function policy(): PasswordPolicy
    {
        return new PasswordPolicy(new LocalCommonPasswords(dirname(__DIR__, 2).'/resources/security/common-passwords.txt'));
    }
}
