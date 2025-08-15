<?php

namespace Core\Support\Facades;

use Core\Contracts\Auth\Authenticatable;
use Core\Support\Facade;

/**
 * Cung cấp một giao diện tĩnh cho AccessControlService.
 *
 * @method static bool check(Authenticatable $user, string $ability, mixed $arguments = [])
 * @see \Modules\User\Domain\Services\AccessControlService
 */
class Gate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gate';
    }
}
