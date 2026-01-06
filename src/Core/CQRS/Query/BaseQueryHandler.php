<?php

namespace Core\CQRS\Query;

use Core\CQRS\Query\Query;

/**
 * Base Query Handler
 * 
 * Abstract base class for query handlers with type safety.
 * Extend this class and specify the query type in the handle method.
 * 
 * @template TQuery of Query
 */
abstract class BaseQueryHandler implements QueryHandler
{
    /**
     * Handle the query
     * 
     * @param TQuery $query
     * @return mixed
     */
    abstract public function handle(Query $query): mixed;
}

