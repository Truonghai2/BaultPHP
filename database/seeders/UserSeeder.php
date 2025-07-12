<?php

namespace Database\Seeders;

use Core\Database\Seeder;
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
        // Check if a user already exists to avoid duplication
        if (User::where('email', 'admin@bault.dev')->exists()) {
            return;
        }

        User::create(['name' => 'Admin', 'email' => 'admin@bault.dev', 'password' => password_hash('password', PASSWORD_DEFAULT)]);
    }
}