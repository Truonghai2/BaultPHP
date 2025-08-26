<?php

namespace Core\Console\Commands\Database;

use Core\Console\Contracts\BaseCommand;
use Core\Database\Seeder;

class DbSeedCommand extends BaseCommand
{
    public function description(): string
    {
        return 'Seed the database with records from a specific seeder or the default DatabaseSeeder.';
    }

    public function signature(): string
    {
        return 'db:seed {--class= : The class name of the seeder to run (e.g., UserSeeder or User/UserSeeder for modules)}';
    }

    public function handle(): int
    {
        $class = $this->option('class');

        if (is_null($class)) {
            $seederClass = 'Database\\Seeders\\DatabaseSeeder';
        } else {
            $seederClass = $this->resolveSeederClass($class);
        }

        if (!class_exists($seederClass)) {
            $this->io->error("Seeder class '{$seederClass}' not found.");
            if (is_null($class)) {
                $this->io->comment("The default seeder 'Database\\Seeders\\DatabaseSeeder' was not found. You can create it using 'php cli make:seeder DatabaseSeeder'.");
            }
            return self::FAILURE;
        }

        /** @var Seeder $seeder */
        $seeder = $this->app->make($seederClass);

        $seeder->setContainer($this->app)->setCommand($this);

        if (!$seeder instanceof Seeder) {
            $this->io->error("Class '{$seederClass}' is not a valid Seeder. It must extend Core\\Database\\Seeder.");
            return self::FAILURE;
        }

        $this->io->info("Seeding: {$seederClass}");
        $seeder->run();

        $this->io->success("Database seeding completed successfully using {$seederClass}.");
        return self::SUCCESS;
    }

    /**
     * Resolve the seeder class name from a short name.
     *
     * @param string $class
     * @return string
     */
    protected function resolveSeederClass(string $class): string
    {
        if (class_exists($class)) {
            return $class;
        }

        if (str_contains($class, '/')) {
            [$module, $seederName] = explode('/', $class, 2);
            $moduleClass = 'Modules\\' . ucfirst($module) . '\\Database\\Seeders\\' . $seederName;
            if (class_exists($moduleClass)) {
                return $moduleClass;
            }
        }

        return 'Database\\Seeders\\' . $class;
    }
}
