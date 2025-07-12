<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Database\Seeders\DatabaseSeeder;

class DbSeedCommand extends BaseCommand
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    public function signature(): string
    {
        return 'db:seed {--class=}';
    }

    public function handle(array $args = []): void
    {
        $class = $this->getOption('class', $args);

        $seederClass = $class ? 'Database\\Seeders\\' . $class : DatabaseSeeder::class;

        if (!class_exists($seederClass)) {
            $this->io->error("Seeder class '{$seederClass}' not found.");
            return;
        }

        $seeder = $this->app->make($seederClass);
        $seeder->setContainer($this->app);

        $this->io->info("Seeding: {$seederClass}");
        $seeder->run();

        $this->io->success('Database seeding completed successfully.');
    }

    protected function getOption(string $name, array $args): ?string
    {
        foreach ($args as $arg) {
            $prefix = "--{$name}=";
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }
        return null;
    }
}