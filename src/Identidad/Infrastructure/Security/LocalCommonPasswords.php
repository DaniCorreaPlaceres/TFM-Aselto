<?php

namespace App\Identidad\Infrastructure\Security;

use App\Identidad\Domain\CommonPasswords;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocalCommonPasswords implements CommonPasswords
{
    public const SHA256 = '68782d6a4a19a4768d5f15dd66bd534e7a33055cc755411e33f16d18c50fdcce';
    private const ADDITIONAL_PASSWORDS = [
        '123456789012345', '1234567890123456', '12345678901234567890',
        'qwertyuiopasdfgh', 'qwertyuiopasdfghjkl', 'passwordpassword',
        'contraseñacontraseña', 'aseltoaseltoaselto', 'correct horse battery staple',
    ];
    /** @var array<string|int, true>|null */
    private ?array $passwords = null;

    public function __construct(#[Autowire('%kernel.project_dir%/resources/security/common-passwords.txt')] private readonly string $file)
    {
    }

    public function contains(#[\SensitiveParameter] string $password): bool
    {
        if (null === $this->passwords) {
            $contents = @file_get_contents($this->file);
            if (false === $contents || !hash_equals(self::SHA256, hash('sha256', $contents))) {
                throw new \RuntimeException('The local password blocklist is missing or damaged.');
            }
            $entries = [...explode("\n", rtrim($contents, "\r\n")), ...self::ADDITIONAL_PASSWORDS];
            $this->passwords = array_fill_keys(array_map(static fn (string $entry): string => mb_strtolower(trim($entry), 'UTF-8'), $entries), true);
        }

        return isset($this->passwords[$password]);
    }
}
