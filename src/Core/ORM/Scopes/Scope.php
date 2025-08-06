<?php

namespace Core\ORM\Scopes;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

interface Scope
{
    /**
     * Áp dụng scope vào một QueryBuilder cho trước.
     *
     * @param  \Core\ORM\QueryBuilder  $builder
     * @param  \Core\ORM\Model  $model
     * @return void
     */
    public function apply(QueryBuilder $builder, Model $model);
}
