<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Core\Support\Facades\Hash;
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
        $user = User::firstOrCreate(
            ['email' => 'admin@bault.dev'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ],
        );

        $this->command?->info('Admin user "admin@bault.dev" created or already exists.');

        $superAdminRole = Role::where('name', 'super-admin')->first();

        if ($superAdminRole) {
            RoleAssignment::firstOrCreate([
                'user_id' => $user->id,
                'role_id' => $superAdminRole->id,
                'context_id' => 1,
            ]);
            $this->command?->info('Ensured "admin@bault.dev" has the "super-admin" role.');
        }
    }
}
