<?php

namespace Core\Auth\Events;

use Core\Contracts\Auth\Authenticatable;

class Login
{
    /**
     * Create a new event instance.
     *
     * @param string $guard The name of the guard.
     * @param \Core\Contracts\Auth\Authenticatable $user The authenticated user.
     * @param bool $remember Whether the user was "remembered".
     */
    public function __construct(
        public string $guard,
        public Authenticatable $user,
        public bool $remember,
    ) {
    }
}
