<?php

namespace Core\Contracts\Auth;

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
