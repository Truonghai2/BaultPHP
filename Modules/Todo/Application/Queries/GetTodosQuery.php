<?php

namespace Modules\Todo\Application\Queries;

use Core\CQRS\Query;

/**
 * Query: Get todos for a user.
 */
class GetTodosQuery extends Query
{
    public function __construct(
        public readonly string $userId,
        public readonly ?int $limit = 20,
        public readonly ?int $offset = 0,
        public readonly ?bool $completed = null
    ) {
        parent::__construct('Todo');
    }
}
