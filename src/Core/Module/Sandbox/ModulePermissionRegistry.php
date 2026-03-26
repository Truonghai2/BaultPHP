<?php

declare(strict_types=1);

namespace Core\Module\Sandbox;

use Core\Application;
use Core\Module\ModuleManifest;

/**
 * Provides the list of declared permissions per module (from module.json).
 *
 * Used by ModulePermissionGate to enforce sandbox: a module may only use
 * resources it declared in manifest "permissions".
 */
final class ModulePermissionRegistry
{
    /** @var array<string, list<string>> module name => list of permission strings */
    private array $cache = [];

    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * Get the list of permissions declared by a module.
     *
     * @return list<string>
     */
    public function getPermissionsForModule(string $moduleName): array
    {
        if (isset($this->cache[$moduleName])) {
            return $this->cache[$moduleName];
        }
        $path = $this->app->basePath("Modules/{$moduleName}/module.json");
        if (!is_file($path)) {
            $this->cache[$moduleName] = [];
            return [];
        }
        try {
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data) || empty($data['enabled']) || $data['enabled'] !== true) {
                $this->cache[$moduleName] = [];
                return [];
            }
            $manifest = ModuleManifest::fromArray($data);
            $this->cache[$moduleName] = array_values(array_unique($manifest->permissions));
            return $this->cache[$moduleName];
        } catch (\Throwable) {
            $this->cache[$moduleName] = [];
            return [];
        }
    }

    /**
     * Check if a module declares a given permission.
     */
    public function hasPermission(string $moduleName, string $permission): bool
    {
        return in_array($permission, $this->getPermissionsForModule($moduleName), true);
    }
}
