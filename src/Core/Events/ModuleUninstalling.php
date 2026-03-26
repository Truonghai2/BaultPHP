<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Module\ModuleManifest;

/** Fired BEFORE a module is uninstalled. Listeners may throw to prevent uninstallation. */
class ModuleUninstalling
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly ?int $userId = null,
    ) {}
}
