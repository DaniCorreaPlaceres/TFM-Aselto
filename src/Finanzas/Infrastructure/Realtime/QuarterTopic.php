<?php

namespace App\Finanzas\Infrastructure\Realtime;

final class QuarterTopic
{
    public static function forUser(string $userId): string
    {
        return 'https://aselto.test/quarter-updates/users/'.$userId;
    }
}
