<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Defines a route for a controller method or a route group for a controller class.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * @param string $uri The URI pattern for the route (used on methods).
     * @param string|null $prefix The URL prefix for all routes in a class (used on classes).
     * @param string $method The HTTP method for the route (used on methods).
     * @param string|null $name The name of the route.
     * @param string|array $middleware Middleware to apply to the route or route group.
     * @param string|null $group The middleware group for the route (e.g., 'web', 'api').
     */
    public function __construct(
        public string $uri = '',
        public string $method = 'GET',
        public ?string $name = null,
        public string|array $middleware = [],
        public ?string $group = null,
        public ?string $prefix = null,
    ) {
    }
}
