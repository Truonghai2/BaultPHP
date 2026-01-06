<?php

namespace Modules\User\Application\Queries\User;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetUserByIdQuery
 * 
 * Query to retrieve a user by ID.
 */
class GetUserByIdQuery implements QueryInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $withRoles = false
    ) {}

    public function getQueryName(): string
    {
        return 'user.user.get_by_id';
    }
}

