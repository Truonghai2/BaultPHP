<?php

namespace Core\Auth\Events;

use Core\Contracts\Auth\Authenticatable;

class Authenticated
{
    /**
     * Create a new event instance.
     *
     * @param string $guard The name of the guard.
     * @param \Core\Contracts\Auth\Authenticatable $user The authenticated user.
     */
    public function __construct(
        public string $guard,
        public Authenticatable $user,
    ) {
    }
}
