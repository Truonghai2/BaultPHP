<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

abstract class Relation
{
    /**
     * The query builder instance for the related model.
     */
    protected QueryBuilder $query;

    /**
     * The parent model instance.
     */
    protected Model $parent;

    public function __construct(QueryBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
    }

    /**
     * Execute the query as a "select" statement.
     * This is primarily used for eager loading.
     *
     * @return array
     */
    public function get(): array
    {
        return $this->query->get();
    }

    /**
     * Get the underlying query builder for the relation.
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults();

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  array   $results
     * @param  string  $relation
     * @return array
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Get the SQL for a sub-select to count the number of related models.
     *
     * @return string
     */
    abstract public function getSelectCountSql(): string;
}
