<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\ORM\MigrationManager;

class MigrateModulesCommand extends BaseCommand
{
    /**
     * Create a new command instance.
     *
     * The MigrationManager is injected by the service container. We also inject
     * the Application container to resolve the configuration.
     */
    public function __construct(private MigrationManager $manager)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'ddd:migrate {--rollback : Rollback the last database migration} {--refresh : Rollback and re-run all migrations} {--status : Show the status of each migration}';
    }

    public function description(): string
    {
        return 'Run database migrations for all modules.';
    }

    /**
     * The core logic of the command.
     * This method executes the migration process.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->comment('Running Database Migrations');

        try {
            // Set the output on the manager for this command execution. This allows the
            // manager to be constructed without a dependency on the console IO,
            // making it more reusable.
            $this->manager->setOutput($this->io);

            $config = $this->app->make('config');
            $migrationPaths = $config->get('database.migrations.paths', []);

            if (empty($migrationPaths)) {
                $this->info("No migration paths have been registered. Nothing to migrate.");
                return 0;
            }

            if ($this->option('status')) {
                $ran = $this->manager->getRan();
                $this->io->section('Ran Migrations:');
                if (empty($ran)) {
                    $this->io->info('No migrations have been run.');
                } else {
                    $this->io->listing($ran);
                }
            } elseif ($this->option('refresh')) {
                $this->io->info('Refreshing migrations: Rolling back all migrations and running them again.');
                $this->manager->reset($migrationPaths);
                $this->manager->run($migrationPaths);
            } elseif ($this->option('rollback')) {
                $this->manager->rollback($migrationPaths);
            } else {
                $this->manager->run($migrationPaths);
            }
        } catch (\Throwable $e) {
            $this->error("An error occurred during migration: " . $e->getMessage());
            return 1;
        }

        $this->info("Migration process completed.");
        return 0;
    }
}