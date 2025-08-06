<?php

namespace Core\Auth;

use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\Guard;

/**
 * A simple guard that holds the authenticated user for the duration of a single request.
 * Ideal for stateless API authentication via tokens.
 */
class RequestGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function check(): bool
    {
        return !is_null($this->user);
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function id()
    {
        return $this->user?->getAuthIdentifier();
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function login(Authenticatable $user): void
    {
        $this->setUser($user);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     */
    public function attempt(array $credentials = []): bool
    {
        // Implement your authentication logic here
        return false;
    }
}
