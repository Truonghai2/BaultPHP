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
            RoleSeeder::class, // Chạy seeder này trước để đảm bảo các Role tồn tại
            UserSeeder::class,
            OAuthClientSeeder::class,
        ]);
    }
}
