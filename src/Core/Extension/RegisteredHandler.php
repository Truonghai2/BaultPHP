<?php

declare(strict_types=1);

namespace Core\Extension;

/**
 * An entry in the extension registry: a handler bound to a named extension point.
 */
final class RegisteredHandler
{
    public function __construct(
        public readonly string $pointName,
        public readonly callable $handler,
        public readonly int $priority,
        /** Optional FQCN for introspection / admin UI */
        public readonly ?string $registeredBy = null,
    ) {}
}
