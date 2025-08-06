<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * An attribute to define a route on a controller method or a group on a controller class.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * @param string $uri The URI pattern for the route.
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param array<int, class-string> $middleware An array of middleware classes for the route.
     * @param string|null $group The middleware group for the route (e.g., 'web', 'api').
     */
    public function __construct(
        public string $uri,
        public string $method = 'GET',
        public array $middleware = [],
        public ?string $group = null
    ) {}
}
