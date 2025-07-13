<?php

namespace Core\ORM\Scopes;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

interface Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(QueryBuilder $builder, Model $model): void;
}