<?php

namespace Core\Support\Facades;

/**
 * @method static string make(string $value, array $options = [])
 * @method static bool check(string $value, string $hashedValue)
 * @method static bool needsRehash(string $hashedValue)
 *
 * @see \Core\Contracts\Hashing\Hasher
 */
class Hash extends Facade
{
    /**
     * Lấy tên đã đăng ký của component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'hash';
    }
}
