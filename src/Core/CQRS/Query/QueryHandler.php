<?php

namespace Core\CQRS\Query;

/**
 * Interface for a class that handles a specific query.
 * 
 * @template TQuery of Query
 */
interface QueryHandler
{
    /**
     * Handle the given query.
     * 
     * @param TQuery $query
     * @return mixed
     */
    public function handle(Query $query): mixed;
}
