<?php

namespace Core\CQRS\Query;

interface QueryBus
{
    public function dispatch(Query $query): mixed;
}
