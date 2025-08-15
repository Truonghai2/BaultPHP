<?php

namespace Core\Console\Commands\Database;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

class DbSeedCommand extends BaseCommand
{
    public function description(): string
    {
        return 'Seed the database with records';
    }

    public function signature(): string
    {
        return 'db:seed {--class= : The class name of the seeder}';
    }

    public function handle(): int
    {
        $class = $this->option('class');

        $seederClass = $class ? 'Database\\Seeders\\' . $class : Core\Console\DatabaseSeeder::class;

        if (!class_exists($seederClass)) {
            $this->io->error("Seeder class '{$seederClass}' not found.");
            return 1;
        }

        // We use the application container to resolve the seeder. This allows the
        // seeder itself to have its own dependencies injected via its constructor,
        // which is a cleaner approach than manual dependency setting.
        $seeder = $this->app->make($seederClass);

        $this->io->info("Seeding: {$seederClass}");
        $seeder->run();

        $this->io->success('Database seeding completed successfully.');
        return 0;
    }
}
