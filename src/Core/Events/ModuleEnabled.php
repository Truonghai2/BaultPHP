<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Module\ModuleManifest;

/** Fired AFTER a module has been successfully enabled. */
class ModuleEnabled
{
    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly ?int $userId = null,
    ) {}
}
