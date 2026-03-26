<?php

declare(strict_types=1);

namespace Core\Module\Declarative;

/**
 * Value object for a module's declarative manifest (routes, permissions, menu).
 *
 * Loaded from Modules/<Name>/manifest.yaml or manifest.json.
 * Reduces PHP boilerplate by declaring routes, permissions, and nav in config.
 */
final class ModuleDeclarativeConfig
{
    /**
     * @param string $moduleName     Module name (e.g. "User")
     * @param string $moduleNamespace Base namespace for the module (e.g. "Modules\\User")
     * @param list<array{method: string, uri: string, action: string, name?: string, middleware?: list<string>, group?: string}> $routes
     * @param list<string> $permissions Permission keys this module declares (for ACL discovery)
     * @param list<array{label: string, url: string, icon?: string, order?: int, children?: array}> $menuAdmin
     * @param list<array{label: string, url: string, order?: int, children?: array}> $menuFrontend
     */
    public function __construct(
        public readonly string $moduleName,
        public readonly string $moduleNamespace,
        public readonly array $routes,
        public readonly array $permissions,
        public readonly array $menuAdmin,
        public readonly array $menuFrontend,
    ) {
    }

    public function hasRoutes(): bool
    {
        return $this->routes !== [];
    }

    public function hasMenu(): bool
    {
        return $this->menuAdmin !== [] || $this->menuFrontend !== [];
    }

    public function hasPermissions(): bool
    {
        return $this->permissions !== [];
    }

    /**
     * Resolve action string (e.g. "ProfileController@show") to a callable array
     * using the module's controller namespace.
     */
    public function resolveAction(string $action): array
    {
        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            $class = trim($class);
            $method = trim($method);
            if (!str_contains($class, '\\')) {
                $class = $this->moduleNamespace . '\\Http\\Controllers\\' . $class;
            }
            return [$class, $method];
        }
        if (str_contains($action, '::')) {
            [$class, $method] = explode('::', $action, 2);
            $class = trim($class);
            $method = trim($method);
            if (!str_contains($class, '\\')) {
                $class = $this->moduleNamespace . '\\Http\\Controllers\\' . $class;
            }
            return [$class, $method];
        }
        return [$this->moduleNamespace . '\\Http\\Controllers\\' . $action, '__invoke'];
    }
}
