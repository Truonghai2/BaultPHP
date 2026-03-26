<?php

declare(strict_types=1);

namespace Core\Module\Sandbox;

use RuntimeException;

/**
 * Thrown when a module attempts an action (e.g. cache write) without
 * declaring the required permission in its manifest.
 */
final class ModulePermissionDeniedException extends RuntimeException
{
    public function __construct(
        string $moduleName,
        string $permission,
    ) {
        parent::__construct(
            "Module '{$moduleName}' is not allowed to perform '{$permission}'. Add it to the module's permissions in module.json.",
        );
    }
}
