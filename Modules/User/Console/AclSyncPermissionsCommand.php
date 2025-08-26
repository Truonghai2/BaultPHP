<?php

namespace Modules\User\Console;

use Core\Console\Contracts\BaseCommand;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;
use Throwable;

class AclSyncPermissionsCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'acl:sync-permissions';
    }

    public function description(): string
    {
        return 'Synchronize permissions from files to the database.';
    }

    public function handle(): int
    {
        $this->io->title('Synchronizing Permissions from Files to Database');

        try {
            $filePermissions = $this->getFilePermissions();
            $dbPermissions = Permission::all()->keyBy('name');
            $this->io->info(count($filePermissions) . ' permissions found in module files.');
            $this->io->info($dbPermissions->count() . ' permissions found in the database.');

            $filePermissionNames = array_keys($filePermissions);
            $dbPermissionNames = $dbPermissions->keys()->all();
            $toAddNames = array_diff($filePermissionNames, $dbPermissionNames);
            $toRemoveNames = array_diff($dbPermissionNames, $filePermissionNames);
            $toCheckNames = array_intersect($filePermissionNames, $dbPermissionNames);

            $changes = $this->performSync($toAddNames, $toCheckNames, $toRemoveNames, $filePermissions, $dbPermissions);
            $this->syncSuperAdminRole();
            $this->reportChanges($changes);
        } catch (Throwable $e) {
            $this->io->error("An error occurred during permission synchronization: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    protected function getFilePermissions(): array
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
                } catch (Throwable $e) {
                    $this->io->warning("Could not load permissions from: {$permissionFile}. Error: {$e->getMessage()}");
                }
            }
        }

        return $permissions;
    }

    private function performSync(array $toAdd, array $toCheck, array $toRemove, array $filePerms, \Core\Support\Collection $dbPerms): array
    {
        $addedCount = $this->syncAdded($toAdd, $filePerms);
        $updatedCount = $this->syncUpdated($toCheck, $filePerms, $dbPerms);
        $removedCount = $this->syncRemoved($toRemove);

        return ['added' => $addedCount, 'updated' => $updatedCount, 'removed' => $removedCount];
    }

    private function syncAdded(array $names, array $filePerms): int
    {
        if (empty($names)) {
            return 0;
        }

        $this->io->section('Adding new permissions:');
        foreach ($names as $name) {
            $details = $filePerms[$name];
            Permission::create([
                'name' => $name,
                'description' => $details['description'] ?? '',
                'captype' => $details['captype'] ?? 'notAll',
            ]);
            $this->io->writeln("  - <fg=green>Added:</> {$name}");
        }
        return count($names);
    }

    private function syncUpdated(array $names, array $filePerms, \Core\Support\Collection $dbPerms): int
    {
        if (empty($names)) {
            return 0;
        }

        $this->io->section('Checking existing permissions for updates:');
        $updatedCount = 0;
        foreach ($names as $name) {
            $fileDetails = $filePerms[$name];
            $dbPermission = $dbPerms[$name];

            $descriptionChanged = ($fileDetails['description'] ?? '') !== $dbPermission->description;
            $captypeChanged = ($fileDetails['captype'] ?? 'notAll') !== $dbPermission->captype;

            if ($descriptionChanged || $captypeChanged) {
                $dbPermission->description = $fileDetails['description'] ?? '';
                $dbPermission->captype = $fileDetails['captype'] ?? 'notAll';
                $dbPermission->save();
                $this->io->writeln("  - <fg=yellow>Updated:</> {$name} (details changed)");
                $updatedCount++;
            }
        }

        if ($updatedCount === 0) {
            $this->io->writeln('No permissions needed an update.');
        }
        return $updatedCount;
    }

    private function syncRemoved(array $names): int
    {
        if (empty($names)) {
            return 0;
        }

        $this->io->section('Removing stale permissions:');
        $numRemoved = Permission::where('name', 'IN', $names)->delete();
        foreach ($names as $name) {
            $this->io->writeln("  - <fg=red>Removed:</> {$name}");
        }
        return $numRemoved;
    }

    /**
     * Ensures the super-admin role has all currently defined permissions.
     */
    private function syncSuperAdminRole(): void
    {
        $this->io->section('Syncing super-admin role permissions:');
        /** @var Role|null $superAdminRole */
        $superAdminRole = Role::where('name', 'super-admin')->first();

        if ($superAdminRole) {
            $allPermissionIds = Permission::all()->pluck('id')->all();
            $superAdminRole->permissions()->sync($allPermissionIds);
            $this->io->writeln(sprintf('  - <fg=cyan>Synced %d permissions to super-admin role.</>', count($allPermissionIds)));
        } else {
            $this->io->warning('  - Could not find "super-admin" role. Please run database seeders.');
        }
    }

    private function reportChanges(array $changes): void
    {
        $this->io->newLine();
        if (array_sum($changes) === 0) {
            $this->io->success('Permissions are already up-to-date.');
        } else {
            $this->io->success(sprintf(
                'Synchronization complete! Added: %d, Updated: %d, Removed: %d.',
                $changes['added'],
                $changes['updated'],
                $changes['removed'],
            ));
        }
    }
}
