<?php

namespace Core\Contracts\Auth;

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
