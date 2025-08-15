<?php

namespace Core\Contracts\Auth;

/**
 * Authenticatable is an interface that defines the methods required for an object to be considered
 * as a user in the authentication system.
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier();

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string;
}
