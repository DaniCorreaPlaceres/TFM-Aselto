<?php

namespace App\Shared\Infrastructure;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DeploymentLock
{
    /** @var resource|null */
    private $handle;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    public function maintenance(): bool
    {
        return file_exists($this->projectDir.'/var/share/deployment.maintenance');
    }

    public function acquire(): bool
    {
        if ($this->maintenance()) {
            return false;
        }
        $directory = $this->projectDir.'/var/share';
        if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0700, true))) {
            throw new \RuntimeException('Deployment lock unavailable.');
        }
        $path = $directory.'/deployment.lock';
        if (is_link($path)) {
            throw new \RuntimeException('Invalid deployment lock.');
        }
        $handle = fopen($path, 'c+');
        if (false === $handle || !chmod($path, 0600) || !flock($handle, LOCK_SH | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return false;
        }
        $this->handle = $handle;
        if ($this->maintenance()) {
            $this->release();

            return false;
        }

        return true;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
