<?php

namespace Core\CQRS\Contracts;

/**
 * Query Handler Interface
 * 
 * Generic interface for query handlers.
 * Handlers should implement this interface with specific query types.
 * 
 * @template TQuery of QueryInterface
 */
interface QueryHandlerInterface
{
    /**
     * Handle the query
     * 
     * @param TQuery $query
     * @return mixed
     */
    public function handle(QueryInterface $query): mixed;
}
