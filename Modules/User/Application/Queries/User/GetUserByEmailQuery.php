<?php

namespace Modules\User\Application\Queries\User;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetUserByEmailQuery
 * 
 * Query to retrieve a user by email address.
 */
class GetUserByEmailQuery implements QueryInterface
{
    public function __construct(
        public readonly string $email,
        public readonly bool $withRoles = false
    ) {}

    public function getQueryName(): string
    {
        return 'user.user.get_by_email';
    }
}

