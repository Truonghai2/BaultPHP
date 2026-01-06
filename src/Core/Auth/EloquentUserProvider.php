<?php

namespace Core\Auth;

use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\UserProvider;

class EloquentUserProvider implements UserProvider
{
    protected string $model;

    /**
     * Request-level cache to avoid duplicate database queries
     * Cache is automatically cleared between requests
     */
    private static array $requestCache = [];

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        // Check request cache first to avoid duplicate queries
        if (isset(self::$requestCache[$identifier])) {
            return self::$requestCache[$identifier];
        }

        $user = $this->model::find($identifier);
        
        // Cache the result for subsequent calls in this request
        if ($user) {
            self::$requestCache[$identifier] = $user;
        }

        return $user;
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        // Generate cache key based on credentials (excluding password)
        $cacheKey = 'cred_' . md5(json_encode(array_diff_key($credentials, ['password' => ''])));
        
        // Check request cache first
        if (isset(self::$requestCache[$cacheKey])) {
            return self::$requestCache[$cacheKey];
        }

        $query = $this->model::query();

        foreach ($credentials as $key => $value) {
            if ($key !== 'password') {
                $query->where($key, $value);
            }
        }

        $user = $query->first();
        
        // Cache the result for subsequent calls in this request
        if ($user) {
            self::$requestCache[$cacheKey] = $user;
        }

        return $user;
    }
}
