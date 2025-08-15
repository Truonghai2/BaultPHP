<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Defines a group of routes for a controller.
 * This allows setting a common prefix, middleware, and name prefix for all routes within the class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(
        public string $prefix = '',
        public string|array $middleware = [],
        public ?string $name = null,
        public ?string $group = null,
    ) {
    }
}
