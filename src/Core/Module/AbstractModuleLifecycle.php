<?php

declare(strict_types=1);

namespace Core\Module;

/**
 * Convenience base class for module lifecycle hooks.
 *
 * Extend this instead of implementing ModuleLifecycle directly so you
 * only need to override the hooks you care about.
 */
abstract class AbstractModuleLifecycle implements ModuleLifecycle
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
