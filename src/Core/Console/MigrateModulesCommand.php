<?php

namespace Core\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\ORM\MigrationManager;

class MigrateModulesCommand extends BaseCommand
{
    protected string $signature = 'ddd:migrate';
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        // If your BaseCommand has a constructor, you might need to call parent::__construct() here.
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function handle(array $args = []): void
    {
        $this->io->title('Running Database Migrations');

        $rollback = in_array('--rollback', $args);
        $refresh = in_array('--refresh', $args);
        $status = in_array('--status', $args);

        $config = $this->app->make('config');
        $migrationPaths = $config->get('database.migrations.paths', []);

        if (empty($migrationPaths)) {
            $this->io->info("No migration paths have been registered. Nothing to migrate.");
            return;
        }

        foreach ($migrationPaths as $path) {
            if (!is_dir($path)) continue;

            // Attempt to extract module name from path for better output
            $module = 'Unknown';
            if (preg_match('/Modules[\\\\\/]([a-zA-Z0-9]+)/', $path, $matches)) {
                $module = $matches[1];
            }
            $this->io->section("Processing module: $module");

            try {
                $manager = new MigrationManager();

                if ($status) {
                    $ran = $manager->getRan();
                    $this->io->listing($ran);
                } elseif ($rollback) {
                    $this->io->warning("Rollback not implemented yet.");
                } elseif ($refresh) {
                    $this->io->warning("Refresh not implemented yet.");
                } else {
                    $manager->runMigrations($path);
                }
            } catch (\Throwable $e) {
                $this->io->error("Error in module $module: " . $e->getMessage());
            }
        }

        $this->io->success("Migration process completed.");
    }
}