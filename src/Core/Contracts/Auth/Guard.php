<?php

namespace Core\Contracts\Auth;

/**
 * Guard is responsible for managing the authentication state of the user.
 */
interface Guard
{
    /**
     * Determine if the current user is authenticated.
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable;

    /**
     * Get the ID for the currently authenticated user.
     */
    public function id();

    /**
     * Set the current user.
     */
    public function setUser(Authenticatable $user): void;

    /**
     * Log a user into the application.
     */
    public function login(Authenticatable $user, bool $remember = false): void;

    /**
     * Log the user out of the application.
     */
    public function logout(): void;

    /**
     * Attempt to authenticate a user using the given credentials.
     */
    public function attempt(array $credentials = [], bool $remember = false): ?Authenticatable;
}
