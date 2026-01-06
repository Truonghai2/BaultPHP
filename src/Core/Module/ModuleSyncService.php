<?php

declare(strict_types=1);

namespace Core\Module;

use Core\Application;
use Core\Support\Facades\Log;
use Modules\User\Console\AclSyncPermissionsCommand;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;

/**
 * Module Sync Service
 *
 * Service to automatically synchronize module data (modules, permissions, roles)
 * from filesystem to database.
 */
class ModuleSyncService
{
    public function __construct(
        private readonly ModuleSynchronizer $moduleSynchronizer,
        private readonly Application $app,
    ) {
    }

    /**
     * Sync all module-related data to database
     *
     * @param string|null $moduleName If provided, only sync this specific module
     * @return array Summary of sync operations
     */
    public function syncAll(?string $moduleName = null): array
    {
        $result = [
            'modules' => [],
            'permissions' => [],
            'roles' => [],
        ];

        try {
            // 1. Sync modules
            Log::info('Starting module synchronization...');
            $result['modules'] = $this->syncModules($moduleName);

            // 2. Sync permissions
            Log::info('Starting permission synchronization...');
            $result['permissions'] = $this->syncPermissions();

            // 3. Sync roles (if module has roles.php)
            Log::info('Starting role synchronization...');
            $result['roles'] = $this->syncRoles($moduleName);

            Log::info('Module synchronization complete', $result);
        } catch (\Throwable $e) {
            Log::error('Error during module synchronization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Sync modules from filesystem to database
     *
     * @param string|null $moduleName
     * @return array
     */
    public function syncModules(?string $moduleName = null): array
    {
        if ($moduleName) {
            // Sync single module
            return $this->syncSingleModule($moduleName);
        }

        // Sync all modules
        return $this->moduleSynchronizer->sync();
    }

    /**
     * Sync a single module
     *
     * @param string $moduleName
     * @return array
     */
    private function syncSingleModule(string $moduleName): array
    {
        $modulePath = base_path("Modules/{$moduleName}");
        if (!is_dir($modulePath)) {
            throw new \RuntimeException("Module directory not found: {$modulePath}");
        }

        $moduleJsonPath = "{$modulePath}/module.json";
        if (!file_exists($moduleJsonPath)) {
            throw new \RuntimeException("module.json not found in {$modulePath}");
        }

        $meta = json_decode(file_get_contents($moduleJsonPath), true);
        if (!isset($meta['name'])) {
            throw new \RuntimeException("Invalid module.json in {$modulePath}");
        }

        $module = Module::where('name', $moduleName)->first();

        if ($module) {
            // Update existing
            $module->version = $meta['version'] ?? $module->version;
            $module->description = $meta['description'] ?? $module->description;
            $module->save();

            return [
                'added' => [],
                'updated' => [$moduleName],
                'removed' => [],
            ];
        }

        // Create new
        Module::create([
            'name' => $meta['name'],
            'version' => $meta['version'] ?? '1.0.0',
            'description' => $meta['description'] ?? '',
            'enabled' => false,
            'status' => 'pending',
        ]);

        return [
            'added' => [$moduleName],
            'updated' => [],
            'removed' => [],
        ];
    }

    /**
     * Sync permissions from module files to database
     *
     * @return array
     */
    public function syncPermissions(): array
    {
        $filePermissions = $this->getFilePermissions();
        $dbPermissions = Permission::all()->keyBy('name');

        $filePermissionNames = array_keys($filePermissions);
        $dbPermissionNames = $dbPermissions->keys()->all();

        $toAddNames = array_diff($filePermissionNames, $dbPermissionNames);
        $toCheckNames = array_intersect($filePermissionNames, $dbPermissionNames);
        $toRemoveNames = array_diff($dbPermissionNames, $filePermissionNames);

        $added = $this->syncAddedPermissions($toAddNames, $filePermissions);
        $updated = $this->syncUpdatedPermissions($toCheckNames, $filePermissions, $dbPermissions);
        $removed = $this->syncRemovedPermissions($toRemoveNames);

        // Sync super-admin role permissions
        $this->syncSuperAdminRole();

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }

    /**
     * Get permissions from all module files
     *
     * @return array
     */
    private function getFilePermissions(): array
    {
        $permissions = [];
        $moduleDirs = glob(base_path('Modules/*'), GLOB_ONLYDIR);

        foreach ($moduleDirs as $dir) {
            $permissionFile = $dir . '/permissions.php';
            if (file_exists($permissionFile)) {
                try {
                    $modulePermissions = require $permissionFile;
                    if (is_array($modulePermissions)) {
                        $permissions = array_merge($permissions, $modulePermissions);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Could not load permissions from: {$permissionFile}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $permissions;
    }

    /**
     * Sync added permissions
     *
     * @param array $names
     * @param array $filePerms
     * @return array
     */
    private function syncAddedPermissions(array $names, array $filePerms): array
    {
        $added = [];
        foreach ($names as $name) {
            $details = $filePerms[$name];
            Permission::create([
                'name' => $name,
                'description' => $details['description'] ?? '',
                'captype' => $details['captype'] ?? 'read',
            ]);
            $added[] = $name;
            Log::info("Added permission: {$name}");
        }
        return $added;
    }

    /**
     * Sync updated permissions
     *
     * @param array $names
     * @param array $filePerms
     * @param \Core\Support\Collection $dbPerms
     * @return array
     */
    private function syncUpdatedPermissions(array $names, array $filePerms, \Core\Support\Collection $dbPerms): array
    {
        $updated = [];
        foreach ($names as $name) {
            $fileDetails = $filePerms[$name];
            $dbPermission = $dbPerms[$name];

            $descriptionChanged = ($fileDetails['description'] ?? '') !== $dbPermission->description;
            $captypeChanged = ($fileDetails['captype'] ?? 'read') !== $dbPermission->captype;

            if ($descriptionChanged || $captypeChanged) {
                $dbPermission->description = $fileDetails['description'] ?? '';
                $dbPermission->captype = $fileDetails['captype'] ?? 'read';
                $dbPermission->save();
                $updated[] = $name;
                Log::info("Updated permission: {$name}");
            }
        }
        return $updated;
    }

    /**
     * Sync removed permissions
     *
     * @param array $names
     * @return array
     */
    private function syncRemovedPermissions(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        Permission::whereIn('name', $names)->delete();
        foreach ($names as $name) {
            Log::info("Removed permission: {$name}");
        }

        return $names;
    }

    /**
     * Sync super-admin role with all permissions
     */
    private function syncSuperAdminRole(): void
    {
        $superAdminRole = Role::where('name', 'super-admin')->first();

        if ($superAdminRole) {
            $allPermissionIds = Permission::all()->pluck('id')->all();
            $superAdminRole->permissions()->sync($allPermissionIds);
            Log::info('Synced super-admin role with all permissions', [
                'permission_count' => count($allPermissionIds),
            ]);
        }
    }

    /**
     * Sync roles from module files to database
     *
     * @param string|null $moduleName
     * @return array
     */
    public function syncRoles(?string $moduleName = null): array
    {
        $result = [
            'added' => [],
            'updated' => [],
            'removed' => [],
        ];

        $moduleDirs = $moduleName
            ? [base_path("Modules/{$moduleName}")]
            : glob(base_path('Modules/*'), GLOB_ONLYDIR);

        foreach ($moduleDirs as $dir) {
            $rolesFile = $dir . '/roles.php';
            if (!file_exists($rolesFile)) {
                continue;
            }

            try {
                $moduleRoles = require $rolesFile;
                if (!is_array($moduleRoles)) {
                    continue;
                }

                foreach ($moduleRoles as $roleName => $roleData) {
                    $role = Role::where('name', $roleName)->first();

                    if ($role) {
                        // Update existing role
                        if (isset($roleData['description'])) {
                            $role->description = $roleData['description'];
                        }
                        $role->save();
                        $result['updated'][] = $roleName;
                        Log::info("Updated role: {$roleName}");
                    } else {
                        // Create new role
                        Role::create([
                            'name' => $roleName,
                            'description' => $roleData['description'] ?? '',
                        ]);
                        $result['added'][] = $roleName;
                        Log::info("Added role: {$roleName}");
                    }

                    // Sync role permissions if specified
                    if (isset($roleData['permissions']) && is_array($roleData['permissions'])) {
                        $this->syncRolePermissions($roleName, $roleData['permissions']);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Could not load roles from: {$rolesFile}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Sync permissions for a specific role
     *
     * @param string $roleName
     * @param array $permissionNames
     */
    private function syncRolePermissions(string $roleName, array $permissionNames): void
    {
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return;
        }

        $permissionIds = Permission::whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        if (!empty($permissionIds)) {
            $role->permissions()->sync($permissionIds);
            Log::info("Synced permissions for role: {$roleName}", [
                'permission_count' => count($permissionIds),
            ]);
        }
    }
}

