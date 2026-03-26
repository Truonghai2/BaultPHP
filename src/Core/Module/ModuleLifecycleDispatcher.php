<?php

declare(strict_types=1);

namespace Core\Module;

use Core\Application;
use Core\Events\ModuleDisabled;
use Core\Events\ModuleDisabling;
use Core\Events\ModuleEnabled;
use Core\Events\ModuleEnabling;
use Core\Events\ModuleInstalled;
use Core\Events\ModuleUninstalled;
use Core\Events\ModuleUninstalling;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orchestrates the module lifecycle:
 *  1. Calls the hook class declared in module.json (if any).
 *  2. Fires the corresponding domain event.
 *
 * All hook errors are caught and logged rather than crashing the operation,
 * unless the hook throws an exception during a "before" phase (onInstalling,
 * onEnabling, onDisabling, onUninstalling) — those propagate so the caller
 * can abort the operation.
 */
final class ModuleLifecycleDispatcher
{
    public function __construct(
        private readonly Application $app,
        private readonly LoggerInterface $logger,
    ) {}

    // -------------------------------------------------------------------------
    // Public API — one method per transition
    // -------------------------------------------------------------------------

    /**
     * Call before the module files are moved and the DB record is created.
     * Throws on hook error so the installation can be aborted.
     */
    public function installing(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onInstalling', propagate: true);
        // No event here — ModuleInstalled is fired after success
    }

    /**
     * Call after the module has been installed and registered in the DB.
     */
    public function installed(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onInstalled', propagate: false);
        $this->fireEvent(new ModuleInstalled($manifest->name, $userId));
    }

    /**
     * Call before enabled = true is written. Throws on hook error → aborts enabling.
     */
    public function enabling(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onEnabling', propagate: true);
        $this->fireEvent(new ModuleEnabling($manifest, $userId));
    }

    /**
     * Call after enabled = true is written.
     */
    public function enabled(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onEnabled', propagate: false);
        $this->fireEvent(new ModuleEnabled($manifest, $userId));
    }

    /**
     * Call before enabled = false is written. Throws on hook error → aborts disabling.
     */
    public function disabling(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onDisabling', propagate: true);
        $this->fireEvent(new ModuleDisabling($manifest, $userId));
    }

    /**
     * Call after enabled = false is written.
     */
    public function disabled(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onDisabled', propagate: false);
        $this->fireEvent(new ModuleDisabled($manifest, $userId));
    }

    /**
     * Call before the module directory and DB record are deleted.
     * Throws on hook error → aborts uninstallation.
     */
    public function uninstalling(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onUninstalling', propagate: true);
        $this->fireEvent(new ModuleUninstalling($manifest, $userId));
    }

    /**
     * Call after the module has been fully removed.
     */
    public function uninstalled(ModuleManifest $manifest, ?int $userId = null): void
    {
        $this->callHook($manifest, 'onUninstalled', propagate: false);
        $this->fireEvent(new ModuleUninstalled($manifest, $userId));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the hook class, instantiate it, and call $method($manifest).
     *
     * @param bool $propagate When true, exceptions bubble up (abort-capable hooks).
     *                        When false, exceptions are only logged.
     */
    private function callHook(ModuleManifest $manifest, string $method, bool $propagate): void
    {
        if (!$manifest->hasHooks()) {
            return;
        }

        try {
            $hookClass = $manifest->hookClass;

            // Resolve via container so the hook can depend-inject services
            $hook = $this->app->make($hookClass);

            if (!$hook instanceof ModuleLifecycle) {
                $this->logger->warning("Module hook class '{$hookClass}' does not implement ModuleLifecycle; skipping.");
                return;
            }

            $hook->{$method}($manifest);

            $this->logger->debug("Module lifecycle hook '{$method}' called for '{$manifest->name}'.", [
                'hook_class' => $hookClass,
            ]);
        } catch (Throwable $e) {
            $this->logger->error("Module lifecycle hook '{$method}' for '{$manifest->name}' failed: {$e->getMessage()}", [
                'hook_class' => $manifest->hookClass,
                'exception' => get_class($e),
            ]);

            if ($propagate) {
                throw $e;
            }
        }
    }

    private function fireEvent(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            $this->logger->warning('Module lifecycle event dispatch failed: ' . $e->getMessage(), [
                'event' => get_class($event),
            ]);
        }
    }
}
