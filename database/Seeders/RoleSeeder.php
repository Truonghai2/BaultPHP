<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder now handles both creating permissions from the config file
     * and assigning them to default roles.
     *
     * @return void
     */
    public function run(): void
    {
        $definedPermissions = $this->syncPermissions();

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Người dùng có toàn quyền quản trị cao nhất trong hệ thống.',
            ],
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Người dùng có quyền quản trị các chức năng được chỉ định.',
            ],
        );

        $superAdminRole->permissions()->sync($definedPermissions->pluck('id'));

        $adminPermissionNames = [
            'users:view',
            'users:create',
            'users:edit',
            'users:delete',
            'roles:assign',
        ];
        $adminPermissions = $definedPermissions->whereIn('name', $adminPermissionNames);
        $adminRole->permissions()->sync($adminPermissions->pluck('id'));
    }

    /**
     * Đọc file định nghĩa, tạo/cập nhật quyền trong CSDL,
     * và xóa các quyền không còn được định nghĩa.
     *
     * @return \Core\Support\Collection
     */
    private function syncPermissions(): \Core\Support\Collection
    {
        $permissions = require base_path('Modules/User/permissions.php');
        $permissionNames = array_keys($permissions);

        foreach ($permissions as $name => $details) {
            Permission::updateOrCreate(['name' => $name], $details);
        }

        Permission::whereNotIn('name', $permissionNames)->delete();

        return Permission::whereIn('name', $permissionNames)->get();
    }
}
