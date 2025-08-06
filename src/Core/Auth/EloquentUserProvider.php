<?php

namespace Core\Auth;

use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\UserProvider;

class EloquentUserProvider implements UserProvider
{
    protected string $model;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->model::find($identifier);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        return $this->model::where('email', $credentials['email'])->first();
    }
}
