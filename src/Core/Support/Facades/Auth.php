<?php

namespace Core\Support\Facades;

use Core\Auth\AuthManager;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;

/**
 * @method static void setUser(Authenticatable $user)
 * @method static Authenticatable|null user()
 * @method static int|string|null id()
 * @method static Guard guard(string|null $name = null)
 * @method static bool check()
 * @method static void reset()
 *
 * @see \Core\Auth\AuthManager
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuthManager::class;
    }
}
