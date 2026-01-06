<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Infrastructure\Models\BlockRegion;

/**
 * Block Region Seeder
 *
 * Seeds default block regions for the CMS.
 */
class BlockRegionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding default block regions...');

        $defaultRegions = [
            ['name' => 'header', 'title' => 'Header', 'description' => 'Top header region', 'max_blocks' => 10, 'is_active' => true],
            ['name' => 'header-nav', 'title' => 'Header Navigation', 'description' => 'Header navigation menu', 'max_blocks' => 5, 'is_active' => true],
            ['name' => 'header-user', 'title' => 'Header User Menu', 'description' => 'Header user menu (login/profile)', 'max_blocks' => 3, 'is_active' => true],
            ['name' => 'sidebar-left', 'title' => 'Left Sidebar', 'description' => 'Left sidebar region', 'max_blocks' => 20, 'is_active' => true],
            ['name' => 'sidebar', 'title' => 'Right Sidebar', 'description' => 'Right sidebar region', 'max_blocks' => 20, 'is_active' => true],
            ['name' => 'content', 'title' => 'Content', 'description' => 'Main content region', 'max_blocks' => 50, 'is_active' => true],
            ['name' => 'footer', 'title' => 'Footer', 'description' => 'Bottom footer region', 'max_blocks' => 10, 'is_active' => true],
            ['name' => 'homepage-hero', 'title' => 'Homepage Hero', 'description' => 'Homepage hero section', 'max_blocks' => 5, 'is_active' => true],
            ['name' => 'homepage-features', 'title' => 'Homepage Features', 'description' => 'Homepage features section', 'max_blocks' => 10, 'is_active' => true],
            ['name' => 'homepage-stats', 'title' => 'Homepage Stats', 'description' => 'Homepage statistics section', 'max_blocks' => 5, 'is_active' => true],
        ];

        $created = 0;
        foreach ($defaultRegions as $regionData) {
            $region = BlockRegion::firstOrCreate(
                ['name' => $regionData['name']],
                $regionData
            );

            if ($region->wasRecentlyCreated) {
                $this->command->info("  ✓ Created region: {$regionData['name']}");
                $created++;
            }
        }

        $this->command->info("✅ Block regions seeded successfully ({$created} new).");
    }
}