<?php

namespace Core\CQRS;

use Core\Support\Result;

/**
 * Query Handler interface.
 * 
 * Query handlers execute queries and return data.
 * They should be:
 * - Read-only (never modify state)
 * - Fast (use optimized read models)
 * - Return Result<T>
 * 
 * Example:
 * class GetTodosQueryHandler implements QueryHandler {
 *     public function handle(Query $query): Result {
 *         $todos = $this->readRepo->getAll($query);
 *         return Result::ok($todos);
 *     }
 * }
 */
interface QueryHandler
{
    /**
     * Execute the query.
     * 
     * @param Query $query
     * @return Result<mixed>
     */
    public function handle(Query $query): Result;

    /**
     * Get the query class this handler handles.
     *
     * @return string
     */
    public function getQueryClass(): string;

    /**
     * Get the bounded context.
     *
     * @return string
     */
    public function getBoundedContext(): string;
}
