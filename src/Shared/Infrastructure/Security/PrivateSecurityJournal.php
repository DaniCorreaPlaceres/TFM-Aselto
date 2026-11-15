<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Clock;
use App\Shared\Domain\SecurityEntry;
use App\Shared\Domain\SecurityJournal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PrivateSecurityJournal implements SecurityJournal
{
    public const MAX_FILE_BYTES = 16_777_216;

    public function __construct(#[Autowire('%kernel.project_dir%/var/security')] private string $directory, private Clock $clock)
    {
    }

    public function directory(): string
    {
        $this->prepare();

        return $this->directory;
    }

    private function prepare(): void
    {
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true)) || !@chmod($this->directory, 0700)) {
            throw new \RuntimeException('Private security storage unavailable.');
        }
    }

    public function append(SecurityEntry $entry): void
    {
        $this->prepare();
        $line = json_encode($entry->data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        if (strlen($line) > 2048) {
            throw new \RuntimeException('Audit entry too large.');
        }
        $path = $this->directory.'/security-'.$entry->at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d').'.jsonl';
        if (is_link($path)) {
            throw new \RuntimeException('Invalid security file.');
        }
        $file = @fopen($path, 'ab');
        if (false === $file) {
            throw new \RuntimeException('Security file unavailable.');
        }
        try {
            if (!chmod($path, 0600) || !flock($file, LOCK_EX)) {
                throw new \RuntimeException('Security file cannot be protected.');
            }
            if ((fstat($file)['size'] ?? 0) + strlen($line) > self::MAX_FILE_BYTES || fwrite($file, $line) !== strlen($line)) {
                throw new \RuntimeException('Security file capacity unavailable.');
            }
            fflush($file);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    /** @return \Generator<string, SecurityEntry> */
    public function scan(?string $after = null): iterable
    {
        $this->prepare();
        if (null !== $after && 1 !== preg_match('/^\d{4}-\d{2}-\d{2}:\d{10}$/', $after)) {
            throw new \InvalidArgumentException('Invalid audit cursor.');
        }
        foreach ($this->files() as $path) {
            $day = substr(basename($path), 9, 10);
            if ($day < $this->cutoff() || $day > $this->today()) {
                continue;
            }
            if (is_link($path)) {
                throw new \RuntimeException('Invalid security file.');
            }
            $file = fopen($path, 'rb');
            if (false === $file) {
                throw new \RuntimeException('Security file unreadable.');
            }
            try {
                if (!flock($file, LOCK_SH)) {
                    throw new \RuntimeException('Security file cannot be locked.');
                }
                $size = fstat($file)['size'] ?? 0;
                if ($size > self::MAX_FILE_BYTES) {
                    throw new \RuntimeException('Security file exceeds capacity.');
                }
                flock($file, LOCK_UN);
                while (ftell($file) < $size && false !== ($line = fgets($file, 2049))) {
                    $cursor = $day.':'.sprintf('%010d', ftell($file));
                    if (!str_ends_with($line, "\n")) {
                        throw new \RuntimeException('Incomplete security entry.');
                    }
                    if (null !== $after && strcmp($cursor, $after) <= 0) {
                        continue;
                    }
                    $data = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                    if (!is_array($data)) {
                        throw new \RuntimeException('Invalid security entry.');
                    }
                    $entry = SecurityEntry::restore($data);
                    if ($entry->at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d') !== $day) {
                        throw new \RuntimeException('Invalid security date.');
                    }
                    yield $cursor => $entry;
                }
            } finally {
                flock($file, LOCK_UN);
                fclose($file);
            }
        }
    }

    public function purge(): int
    {
        $this->prepare();
        $removed = 0;
        foreach ($this->files() as $path) {
            if (substr(basename($path), 9, 10) < $this->cutoff()) {
                if (is_link($path) || !unlink($path)) {
                    throw new \RuntimeException('Cannot apply security retention.');
                }
                ++$removed;
            }
        }

        return $removed;
    }

    public function healthy(): bool
    {
        try {
            $this->prepare();
            if (!is_writable($this->directory) || (fileperms($this->directory) & 0077) !== 0) {
                return false;
            }
            foreach ($this->files() as $path) {
                if (is_link($path) || (fileperms($path) & 0077) !== 0 || filesize($path) >= self::MAX_FILE_BYTES) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = array_values(array_filter(glob($this->directory.'/security-*.jsonl') ?: [], static fn (string $path): bool => 1 === preg_match('/^security-\d{4}-\d{2}-\d{2}\.jsonl$/', basename($path))));
        sort($files, SORT_STRING);

        return $files;
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
    }

    private function cutoff(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0)->modify('-29 days')->format('Y-m-d');
    }
}
