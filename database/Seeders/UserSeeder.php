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
        try {
            $user = User::firstOrCreate(
                ['email' => 'admin@bault.dev'],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make('password'),
                ],
            );
        } catch (\Throwable $e) {
            $this->command?->error('Failed to create admin user "admin@bault.dev". Error: ' . $e->getMessage());
            // Log the full stack trace for detailed debugging
            \Core\Support\Facades\Log::error('UserSeeder failed', ['exception' => $e]);
            return;
        }
        
        if (!$user) {
            $this->command?->error('Could not find or create the admin user "admin@bault.dev". Aborting role assignment.');
            return;
        }

        if ($user->wasRecentlyCreated) {
            $this->command?->info('Admin user "admin@bault.dev" created.');
        } else {
            $this->command?->info('Admin user "admin@bault.dev" already exists.');
        }

        $superAdminRole = Role::where('name', 'super-admin')->firstOrFail();

        RoleAssignment::firstOrCreate(
            [
            'user_id' => $user->id,
            'role_id' => $superAdminRole->id,
            'context_id' => 1,
        ],
        );

        $this->command?->line('  - Ensured "admin@bault.dev" has the "super-admin" role in the system context.');
    }
}
