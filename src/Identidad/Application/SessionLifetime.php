<?php

namespace App\Identidad\Application;

final class SessionLifetime
{
    public function __construct(private readonly int $idleSeconds = 1800, private readonly int $absoluteSeconds = 28800)
    {
        if ($idleSeconds < 1 || $absoluteSeconds < $idleSeconds) {
            throw new \InvalidArgumentException('Session timeouts must be positive and idle cannot exceed absolute.');
        }
    }

    /** @return array{started_at: int, last_activity_at: int} */
    public function start(int $now): array
    {
        return ['started_at' => $now, 'last_activity_at' => $now];
    }

    public function expired(mixed $state, int $now): bool
    {
        return !is_array($state)
            || !isset($state['started_at'], $state['last_activity_at'])
            || !is_int($state['started_at']) || !is_int($state['last_activity_at'])
            || $state['started_at'] < 0 || $state['last_activity_at'] < $state['started_at']
            || $state['last_activity_at'] > $now
            || $now - $state['started_at'] >= $this->absoluteSeconds
            || $now - $state['last_activity_at'] >= $this->idleSeconds;
    }

    /** @return array{started_at: int, last_activity_at: int} */
    public function activity(mixed $state, int $now): array
    {
        if ($this->expired($state, $now)) {
            throw new \LogicException('An expired session cannot be renewed.');
        }

        return ['started_at' => $state['started_at'], 'last_activity_at' => $now];
    }
}
