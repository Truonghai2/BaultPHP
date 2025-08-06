<?php

namespace Core\ORM\Scopes;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class SoftDeletingScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Core\ORM\QueryBuilder  $builder
     * @param  \Core\ORM\Model  $model
     * @return void
     */
    public function apply(QueryBuilder $builder, Model $model): void
    {
        $builder->whereNull($model->getQualifiedDeletedAtColumn());
    }
}
