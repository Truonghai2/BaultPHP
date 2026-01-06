<?php

namespace Core\CQRS\Contracts;

/**
 * Query Handler Interface
 */
interface QueryHandlerInterface
{
    /**
     * Handle the query
     */
    public function handle(QueryInterface $query): mixed;
}
