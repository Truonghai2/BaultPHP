<?php

declare(strict_types=1);

namespace Core;

use Core\Module\ModuleLifecycle;
use Core\Module\ModuleManifest;

/**
 * Provides empty lifecycle stubs so any class using this trait automatically
 * implements ModuleLifecycle without requiring method overrides.
 *
 * Usage: add `implements ModuleLifecycle` to the class that uses this trait.
 */
trait ModuleLifecycleStubs
{
    public function onInstalling(ModuleManifest $manifest): void {}

    public function onInstalled(ModuleManifest $manifest): void {}

    public function onEnabling(ModuleManifest $manifest): void {}

    public function onEnabled(ModuleManifest $manifest): void {}

    public function onDisabling(ModuleManifest $manifest): void {}

    public function onDisabled(ModuleManifest $manifest): void {}

    public function onUninstalling(ModuleManifest $manifest): void {}

    public function onUninstalled(ModuleManifest $manifest): void {}
}
