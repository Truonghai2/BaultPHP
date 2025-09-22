<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\User\Infrastructure\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding Roles and Permissions...');

        $this->truncateTables(['roles', 'permissions', 'permission_role']);

        $rolesToCreate = [
            'super-admin' => [
                'display_name' => 'Super Administrator',
                'description' => 'Người dùng có toàn quyền quản trị cao nhất trong hệ thống.',
            ],
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Người dùng có quyền quản trị các chức năng được chỉ định.',
            ],
            'editor' => [
                'display_name' => 'Editor',
                'description' => 'Người dùng có quyền tạo và quản lý nội dung.',
            ],
            'member' => [
                'display_name' => 'Member',
                'description' => 'Người dùng thông thường của hệ thống.',
            ],
        ];

        foreach ($rolesToCreate as $roleName => $details) {
            Role::firstOrCreate(
                ['name' => $roleName],
                $details
            );
            $this->command?->line("  - Ensured role '{$roleName}' exists.");
        }

        $this->command?->info('Roles created. Now synchronizing permissions from files...');
        $this->command?->call('acl:sync-permissions');
    }
}
