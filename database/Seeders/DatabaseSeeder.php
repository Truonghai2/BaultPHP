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
        // Gọi các seeder khác từ đây.
        // $this->call(UserSeeder::class);
        $this->call(OAuthClientSeeder::class);
    }
}
