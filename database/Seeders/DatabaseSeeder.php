<?php

namespace Database\Seeders;

use Core\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command?->info('Running main database seeder...');

        $this->call([
            ContextSeeder::class,
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            OAuthClientSeeder::class,
        ]);
    }
}
