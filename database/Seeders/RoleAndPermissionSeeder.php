<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Core\ORM\Connection;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        /** @var \PDO $pdo */
        $pdo = app(Connection::class)->connection();

        $pdo->statement('SET FOREIGN_KEY_CHECKS=0;');

        Role::query()->truncate();
        Permission::query()->truncate();
        $pdo->statement('TRUNCATE TABLE role_has_permissions');

        $pdo->statement('SET FOREIGN_KEY_CHECKS=1;');

        $rolesAndPermissions = [
            'super-admin' => [
                'system:manage-all' => 'Toàn quyền quản trị hệ thống',
            ],
            'editor' => [
                'post:create' => 'Tạo bài viết mới',
                'post:update' => 'Cập nhật bài viết',
                'post:delete' => 'Xóa bài viết',
                'post:view'   => 'Xem bài viết',
            ],
            'member' => [
                'post:view' => 'Xem bài viết',
                'comment:create' => 'Tạo bình luận',
            ],
        ];

        foreach ($rolesAndPermissions as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::create(['name' => $roleName]);
            $this->command->info("Đã tạo Role: {$roleName}");

            $permissionIds = [];
            foreach ($permissions as $permissionName => $description) {
                /** @var Permission $permission */
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['description' => $description],
                );
                $permissionIds[] = $permission->id;
                $this->command->getOutput()->writeln("  - Gán Permission: {$permissionName}");
            }

            if (!empty($permissionIds)) {
                $role->permissions()->attach($permissionIds);
            }
        }
    }
}
