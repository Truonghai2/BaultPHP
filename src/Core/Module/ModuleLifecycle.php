<?php

declare(strict_types=1);

namespace Core\Module;

/**
 * Interface for module lifecycle hooks.
 *
 * A module can declare a hook class in its module.json:
 *   { "hooks": "Modules\\MyModule\\MyModuleLifecycle" }
 *
 * The hook class should implement this interface. Each method is called at the
 * appropriate point in the module lifecycle. Implementations should be idempotent
 * where possible (e.g. safe to call onInstalled twice after a reinstall).
 *
 * Usage example (Modules/User/UserModuleLifecycle.php):
 *
 *   class UserModuleLifecycle extends AbstractModuleLifecycle
 *   {
 *       public function onInstalled(): void
 *       {
 *           // seed default roles, run custom setup …
 *       }
 *
 *       public function onEnabling(): void
 *       {
 *           // validate dependencies before enabling …
 *       }
 *   }
 */
interface ModuleLifecycle
{
    /**
     * Called BEFORE files are copied and the module is registered.
     * Throw an exception to abort the installation.
     */
    public function onInstalling(ModuleManifest $manifest): void;

    /**
     * Called AFTER the module files are in place and the DB record is created.
     * Good place to seed initial data or warm caches.
     */
    public function onInstalled(ModuleManifest $manifest): void;

    /**
     * Called BEFORE the module is set to enabled = true.
     * Throw an exception to prevent enabling.
     */
    public function onEnabling(ModuleManifest $manifest): void;

    /**
     * Called AFTER the module has been enabled.
     */
    public function onEnabled(ModuleManifest $manifest): void;

    /**
     * Called BEFORE the module is set to enabled = false.
     * Throw an exception to prevent disabling (use sparingly).
     */
    public function onDisabling(ModuleManifest $manifest): void;

    /**
     * Called AFTER the module has been disabled.
     */
    public function onDisabled(ModuleManifest $manifest): void;

    /**
     * Called BEFORE the module directory and DB record are deleted.
     * Throw an exception to abort uninstallation.
     */
    public function onUninstalling(ModuleManifest $manifest): void;

    /**
     * Called AFTER the module has been fully removed.
     * Note: the module directory no longer exists at this point.
     */
    public function onUninstalled(ModuleManifest $manifest): void;
}
