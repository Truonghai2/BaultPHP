<?php

namespace Modules\Todo\Application\QueryHandlers;

use Core\CQRS\{Query, QueryHandler};
use Core\Support\Result;
use Modules\Todo\Infrastructure\Repositories\TodoReadRepository;

/**
 * Handler: Get Todos Query.
 * 
 * Reads from optimized read model.
 */
class GetTodosQueryHandler implements QueryHandler
{
    public function __construct(
        private TodoReadRepository $readRepo
    ) {}

    public function handle(Query $query): Result
    {
        // Read from optimized read model
        $todos = $this->readRepo->getByUserId(
            userId: $query->userId,
            limit: $query->limit,
            offset: $query->offset,
            completed: $query->completed
        );

        return Result::ok($todos);
    }

    public function getQueryClass(): string
    {
        return GetTodosQuery::class;
    }

    public function getBoundedContext(): string
    {
        return 'Todo';
    }
}
