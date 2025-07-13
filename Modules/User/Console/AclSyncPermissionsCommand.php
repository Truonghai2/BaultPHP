<?php

namespace Modules\User\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\User\Infrastructure\Models\Permission;

class AclSyncPermissionsCommand extends BaseCommand
{
    public function __construct(protected Application $app)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'acl:sync-permissions';
    }

    public function handle(array $args = []): void
    {
        $this->io->title('Synchronizing Permissions from Files to Database');

        // 1. Lấy tất cả quyền từ các file permissions.php
        $filePermissions = $this->getFilePermissions();
        $this->io->info(count($filePermissions) . ' permissions found in module files.');

        // 2. Lấy tất cả quyền từ CSDL
        $dbPermissions = Permission::all()->map(fn($p) => $p->name)->all();
        $this->io->info(count($dbPermissions) . ' permissions found in the database.');

        // 3. Tính toán quyền cần thêm và xóa
        $toAdd = array_diff($filePermissions, $dbPermissions);
        $toRemove = array_diff($dbPermissions, $filePermissions);

        // 4. Thực hiện thêm quyền mới
        if (!empty($toAdd)) {
            $this->io->section('Adding new permissions:');
            foreach ($toAdd as $permissionName) {
                Permission::create(['name' => $permissionName]);
                $this->io->writeln("  - <fg=green>Added:</> {$permissionName}");
            }
        } else {
            $this->io->writeln('No new permissions to add.');
        }

        // 5. Thực hiện xóa quyền không còn dùng
        if (!empty($toRemove)) {
            $this->io->section('Removing stale permissions:');
            Permission::where('name', 'IN', $toRemove)->delete();
            foreach ($toRemove as $permissionName) {
                $this->io->writeln("  - <fg=red>Removed:</> {$permissionName}");
            }
        } else {
            $this->io->writeln('No stale permissions to remove.');
        }

        $this->io->success('Permissions synchronized successfully!');
    }

    protected function getFilePermissions(): array
    {
        $permissions = [];
        $moduleDirs = glob(base_path('Modules/*'), GLOB_ONLYDIR);

        foreach ($moduleDirs as $dir) {
            $permissionFile = $dir . '/permissions.php';
            if (file_exists($permissionFile)) {
                $modulePermissions = require $permissionFile;
                if (is_array($modulePermissions)) {
                    $permissions = array_merge($permissions, $modulePermissions);
                }
            }
        }

        return array_unique($permissions);
    }
}