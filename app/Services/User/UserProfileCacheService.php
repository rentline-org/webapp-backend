<?php

namespace App\Services\User;

use Closure;
use Illuminate\Support\Facades\Cache;

class UserProfileCacheService
{
    public static function key(int $userId): string
    {
        return "user:{$userId}:profile";
    }

    public static function remember(int $userId, Closure $callback, int $ttlMinutes = 10): mixed
    {
        return Cache::remember(
            self::key($userId),
            now()->addMinutes($ttlMinutes),
            $callback
        );
    }

    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }
}
