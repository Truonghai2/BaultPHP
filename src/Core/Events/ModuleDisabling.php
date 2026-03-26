<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Module\ModuleManifest;

/** Fired BEFORE a module is disabled. Listeners may throw to prevent disabling. */
class ModuleDisabling
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly ?int $userId = null,
    ) {}
}
