<?php

namespace Core\CQRS\Query;

/**
 * Interface for a class that handles a specific query.
 */
interface QueryHandler
{
    /**
     * Handle the given query.
     */
    public function handle(Query $query): mixed;
}
