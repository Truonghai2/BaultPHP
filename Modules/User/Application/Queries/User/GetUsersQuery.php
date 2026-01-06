<?php

namespace Modules\User\Application\Queries\User;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetUsersQuery
 *
 * Query to retrieve a list of users with optional filters.
 */
class GetUsersQuery implements QueryInterface
{
    public function __construct(
        public readonly ?int $limit = null,
        public readonly ?int $offset = null,
        public readonly ?string $search = null,
        public readonly bool $withRoles = false,
    ) {
    }

    public function getQueryName(): string
    {
        return 'user.user.get_all';
    }
}
