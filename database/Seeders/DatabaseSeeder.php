<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Domain\Services\BlockSynchronizer;

/**
 * Main Database Seeder
 *
 * Seeds all essential data in correct order:
 * 1. Context & Auth (Users, Roles, Permissions)
 * 2. Block Types (from real Domain\Blocks classes)
 * 3. Block Regions & Instances (global blocks)
 * 4. Pages (CMS pages)
 * 5. Page Blocks (page-specific blocks)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command?->info('🌱 Running BaultFrame Database Seeder...');
        $this->command?->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command?->info('');

        $this->call([
            // 1. Core System
            ContextSeeder::class,              // Context types
            RoleAndPermissionSeeder::class,    // Roles & Permissions
            UserSeeder::class,                 // Admin user
            // OAuthClientSeeder::class,          // OAuth clients
        ]);

        // 2. Sync Block Types from code to database
        $this->command->info('Synchronizing block types from source code...');
        app(BlockSynchronizer::class)->sync();
        $this->command->info('✅ Block types synchronized.');

        $this->call([
            // 2. Block System
            BlockRegionSeeder::class,          // Default block regions (header, footer, etc.)
            DefaultBlocksSeeder::class,        // Global block instances for default regions

            // 3. CMS Pages & Templates
            PageTemplateSeeder::class,         // Page templates (7 defaults)
            PageSeeder::class,                 // Pages (home, about, etc.)
            PageBlockIntegrationSeeder::class, // Assign blocks to pages
        ]);

        $this->command?->info('');
        $this->command?->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command?->info('✅ All seeders completed successfully!');
        $this->command?->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command?->info('');
        $this->command?->info('Next steps:');
        $this->command?->info('  1. Visit http://localhost to see the homepage');
        $this->command?->info('  2. Login at /login with admin credentials');
        $this->command?->info('  3. Manage blocks at /admin/blocks');
        $this->command?->info('');
    }
}
