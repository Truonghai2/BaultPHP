<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Module\ModuleManifest;

/** Fired AFTER a module has been disabled. */
class ModuleDisabled
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly ?int $userId = null,
    ) {}
}
