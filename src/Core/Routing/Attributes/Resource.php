<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * An attribute to define a full resource controller.
 * This will automatically map to index, create, store, show, edit, update, and destroy methods.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Resource
{
    public function __construct(
        public string $uri,
        public array $options = [],
    ) {
    }
}
