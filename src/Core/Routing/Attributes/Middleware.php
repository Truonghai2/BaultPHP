<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * An attribute to apply one or more middleware to all routes on a controller method.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public function __construct(
        public string|array $middleware
    ) {
    }
}

