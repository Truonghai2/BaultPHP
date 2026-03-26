<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Extension\CoreExtensionPoints as EP;
use Core\Extension\ExtensionPointType;
use Core\Extension\ExtensionRegistry;
use Core\Module\Composer\ComposerModuleDiscovery;
use Core\Module\Declarative\DeclarativeConfigLoader;
use Core\Module\ModulePathResolver;
use Core\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Bootstraps the Extension Point system.
 *
 * Responsibilities:
 *  1. Register ExtensionRegistry as a container singleton.
 *  2. Declare all core-owned extension points.
 *  3. Boot: scan every enabled module for an extensions.php file and load it.
 *
 * Module extensions.php contract
 * ───────────────────────────────
 * The file must return an associative array whose keys are extension-point names
 * and whose values are callables (or [Class::class, 'method'] tuples):
 *
 *   <?php
 *   use Core\Extension\CoreExtensionPoints as EP;
 *
 *   return [
 *       EP::VIEW_GLOBAL_DATA => [MyExtensions::class, 'globalData'],
 *       EP::BLOCK_RENDER     => fn ($html, $ctx) => strtoupper($html), // toy example
 *   ];
 *
 * Entries may optionally be wrapped in a tuple to supply priority:
 *
 *   EP::BLOCK_RENDER => [
 *       ['handler' => [MyDecorator::class, 'decorate'], 'priority' => 20],
 *   ],
 */
class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExtensionRegistry::class, function ($app) {
            $strict = (bool) config('app.extension_strict', false);
            return new ExtensionRegistry(
                $app->make(LoggerInterface::class),
                $strict,
            );
        });

        $this->app->alias(ExtensionRegistry::class, 'extensions');
    }

    public function boot(): void
    {
        /** @var ExtensionRegistry $registry */
        $registry = $this->app->make(ExtensionRegistry::class);

        $this->declareCorePoints($registry);
        $this->loadModuleExtensions($registry);
        $this->registerDeclarativeNavigation($registry);
    }

    // =========================================================================
    // Core declarations
    // =========================================================================

    private function declareCorePoints(ExtensionRegistry $registry): void
    {
        // View
        $registry->declare(EP::VIEW_GLOBAL_DATA,       ExtensionPointType::COLLECTOR, 'Add variables to every view');
        $registry->declare(EP::VIEW_RENDER,            ExtensionPointType::FILTER,    'Transform rendered HTML');

        // Block / CMS
        $registry->declare(EP::BLOCK_RENDER,           ExtensionPointType::FILTER,    'Transform a block\'s rendered HTML');
        $registry->declare(EP::BLOCK_TYPES,            ExtensionPointType::COLLECTOR, 'Register additional block types');

        // HTTP
        $registry->declare(EP::HTTP_GLOBAL_MIDDLEWARE, ExtensionPointType::COLLECTOR, 'Add global HTTP middleware');
        $registry->declare(EP::HTTP_ROUTE_MIDDLEWARE,  ExtensionPointType::COLLECTOR, 'Register named middleware aliases');
        $registry->declare(EP::RESPONSE_BEFORE_SEND,   ExtensionPointType::FILTER,    'Transform PSR-7 response before sending');

        // ACL / Auth
        $registry->declare(EP::ACL_CHECK,              ExtensionPointType::FILTER,    'Override permission check result');
        $registry->declare(EP::AUTH_AUTHENTICATED,     ExtensionPointType::ACTION,    'User authenticated');

        // Navigation
        $registry->declare(EP::NAVIGATION_ADMIN,       ExtensionPointType::COLLECTOR, 'Add admin navigation items');
        $registry->declare(EP::NAVIGATION_FRONTEND,    ExtensionPointType::COLLECTOR, 'Add front-end navigation items');

        // Scheduling
        $registry->declare(EP::SCHEDULE_TASKS,         ExtensionPointType::ACTION,    'Register scheduled tasks');

        // Module
        $registry->declare(EP::MODULE_BOOTED,          ExtensionPointType::ACTION,    'Module finished booting');
        $registry->declare(EP::MODULE_COMMANDS,        ExtensionPointType::FILTER,    'Alter CLI commands for a module');

        // Queue
        $registry->declare(EP::QUEUE_JOB_FAILED,      ExtensionPointType::ACTION,    'Job failed after all retries');
    }

    // =========================================================================
    // Module extension loading
    // =========================================================================

    private function loadModuleExtensions(ExtensionRegistry $registry): void
    {
        $pathResolver = $this->app->bound(ModulePathResolver::class) ? $this->app->make(ModulePathResolver::class) : null;
        foreach ($this->getEnabledModules() as $moduleName) {
            $basePath = $pathResolver ? $pathResolver->pathFor($moduleName) : $this->app->basePath('Modules/' . $moduleName);
            $file = $basePath . '/extensions.php';

            if (!file_exists($file)) {
                continue;
            }

            try {
                $definitions = require $file;
            } catch (Throwable $e) {
                error_log("[ExtensionServiceProvider] Failed to load extensions.php for module '{$moduleName}': {$e->getMessage()}");
                continue;
            }

            if (!is_array($definitions)) {
                error_log("[ExtensionServiceProvider] extensions.php for module '{$moduleName}' must return an array.");
                continue;
            }

            $this->registerDefinitions($registry, $moduleName, $definitions);
        }
    }

    /**
     * Process a module's definitions array.
     *
     * Supports two formats per key:
     *
     *  A) Single handler (any callable):
     *     EP::BLOCK_RENDER => [MyClass::class, 'handle']
     *
     *  B) Multiple handlers with optional priority:
     *     EP::BLOCK_RENDER => [
     *         [MyClass::class, 'handleA'],                         // priority = 10
     *         ['handler' => [OtherClass::class, 'handleB'], 'priority' => 5],
     *     ]
     */
    private function registerDefinitions(
        ExtensionRegistry $registry,
        string $moduleName,
        array $definitions,
    ): void {
        foreach ($definitions as $pointName => $entry) {
            if (!is_string($pointName)) {
                continue;
            }

            // Normalise: wrap single handler in a list
            $entries = $this->normaliseEntry($entry);

            foreach ($entries as [$handler, $priority]) {
                if (!is_callable($handler)) {
                    // Resolve lazy string callables later — accept [ClassName::class, 'method'] pairs
                    if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
                        $handler = $this->makeCallable($handler);
                    } else {
                        error_log("[ExtensionServiceProvider] Non-callable handler for '{$pointName}' in module '{$moduleName}'. Skipping.");
                        continue;
                    }
                }

                $registry->register($pointName, $handler, $priority, $moduleName);
            }
        }
    }

    /**
     * Normalise an entry into a list of [callable, priority] pairs.
     *
     * @return list<array{0: mixed, 1: int}>
     */
    private function normaliseEntry(mixed $entry): array
    {
        // Format B: list of handler specs
        if (is_array($entry) && isset($entry[0]) && is_array($entry[0])) {
            $result = [];
            foreach ($entry as $spec) {
                if (is_array($spec) && isset($spec['handler'])) {
                    $result[] = [$spec['handler'], (int) ($spec['priority'] ?? 10)];
                } else {
                    $result[] = [$spec, 10];
                }
            }
            return $result;
        }

        // Format A: single callable
        return [[$entry, 10]];
    }

    /**
     * Turn a [ClassName::class, 'method'] pair into a true callable that
     * resolves the class from the container at call time (lazy).
     */
    private function makeCallable(array $pair): callable
    {
        [$class, $method] = $pair;
        $app = $this->app;

        return function () use ($class, $method, $app) {
            $instance = $app->make($class);
            return $instance->{$method}(...func_get_args());
        };
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getEnabledModules(): array
    {
        $cachedPath = $this->app->basePath('bootstrap/cache/modules.php');

        if (file_exists($cachedPath)) {
            return require $cachedPath;
        }

        $modules = [];
        foreach (glob($this->app->basePath('Modules/*/module.json')) ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);
            if (!empty($data['name']) && ($data['enabled'] ?? false) === true) {
                $modules[] = $data['name'];
            }
        }
        if ($this->app->bound(ComposerModuleDiscovery::class)) {
            foreach ($this->app->make(ComposerModuleDiscovery::class)->getManifests() as $manifest) {
                $modules[] = $manifest->name;
            }
        }
        return array_values(array_unique($modules));
    }

    /**
     * Register navigation items from declarative manifests (manifest.yaml menu.admin / menu.frontend).
     */
    private function registerDeclarativeNavigation(ExtensionRegistry $registry): void
    {
        if (!$this->app->bound(DeclarativeConfigLoader::class)) {
            return;
        }
        $loader = $this->app->make(DeclarativeConfigLoader::class);
        $registry->register(EP::NAVIGATION_ADMIN, function (array $context) use ($loader): array {
            $items = [];
            foreach ($loader->loadAll() as $config) {
                foreach ($config->menuAdmin as $item) {
                    $items[] = $item;
                }
            }
            return $items;
        }, 5, 'core.declarative');
        $registry->register(EP::NAVIGATION_FRONTEND, function (array $context) use ($loader): array {
            $items = [];
            foreach ($loader->loadAll() as $config) {
                foreach ($config->menuFrontend as $item) {
                    $items[] = $item;
                }
            }
            return $items;
        }, 5, 'core.declarative');
    }
}
