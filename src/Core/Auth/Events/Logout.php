<?php

namespace Core\Auth\Events;

use Core\Contracts\Auth\Authenticatable;

class Logout
{
    /**
     * Create a new event instance.
     *
     * @param string $guard The name of the guard.
     * @param \Core\Contracts\Auth\Authenticatable|null $user The user that was logged out.
     */
    public function __construct(
        public string $guard,
        public ?Authenticatable $user,
    ) {
    }
}
