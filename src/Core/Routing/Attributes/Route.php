<?php

namespace Core\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $uri,
        public string $method = 'GET',
        public array $middleware = []
    ) {
    }
}