<?php

namespace Core\Contracts\Auth;

/**
 * UserProvider is responsible for retrieving user information from a data source.
 */
interface UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier): ?Authenticatable;

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;
}
