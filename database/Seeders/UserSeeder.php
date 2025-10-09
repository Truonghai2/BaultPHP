<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Core\Support\Facades\Hash;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command?->info('Syncing permissions from files...');
        $this->syncPermissions();
        $this->command?->info('Permissions synced.');

        $user = User::firstOrCreate(
            ['email' => 'admin@bault.dev'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ],
        );

        $this->command?->info('Admin user "admin@bault.dev" created or already exists.');

        $superAdminRole = Role::where('name', 'super-admin')->firstOrFail();

        $allPermissionIds = Permission::all()->pluck('id')->all();
        $superAdminRole->permissions()->sync($allPermissionIds);
        $this->command?->info('Synced all permissions to super-admin role.');

        RoleAssignment::updateOrCreate([
            'user_id' => $user->id,
            'role_id' => $superAdminRole->id,
            'context_id' => 1,
        ]);

        $this->command?->info('Ensured "admin@bault.dev" has the "super-admin" role.');
    }

    /**
     * Synchronize permissions from module files to the database.
     * This method ensures the permissions table is up-to-date with the code.
     */
    private function syncPermissions(): void
    {
        $filePermissions = [];
        $moduleDirs = glob(base_path('Modules/*'), GLOB_ONLYDIR);

        foreach ($moduleDirs as $dir) {
            $permissionFile = $dir . '/permissions.php';
            if (file_exists($permissionFile)) {
                $modulePermissions = require $permissionFile;
                if (is_array($modulePermissions)) {
                    $filePermissions = array_merge($filePermissions, $modulePermissions);
                }
            }
        }

        foreach ($filePermissions as $name => $details) {
            \Modules\User\Infrastructure\Models\Permission::updateOrCreate(['name' => $name], $details);
        }
    }
}
