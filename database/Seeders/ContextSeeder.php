<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\User\Infrastructure\Models\Context;

class ContextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder ensures the root "System Context" exists. This context is the
     * foundation of the entire permission hierarchy, representing the highest
     * level of access. All other contexts will be descendants of this one.
     * The ID is hard-coded to 1 as it's referenced by other seeders and services.
     *
     * @return void
     */
    public function run(): void
    {
        Context::updateOrCreate(
            ['id' => 1],
            [
                'parent_id' => null,
                'context_level' => 'system',
                'instance_id' => 0,
                'depth' => 0,
                'path' => '1/',
            ],
        );

        $this->command?->info('System root context created or updated successfully.');
    }
}
