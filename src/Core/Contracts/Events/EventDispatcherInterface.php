<?php

namespace Core\Contracts\Events;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
