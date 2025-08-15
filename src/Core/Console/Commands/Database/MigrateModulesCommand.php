<?php

namespace Core\Console\Commands\Database;

use Core\Console\Contracts\BaseCommand;
use Core\ORM\MigrationManager;

class MigrateModulesCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'ddd:migrate {--rollback : Rollback the last database migration} {--refresh : Rollback and re-run all migrations} {--status : Show the status of each migration} {--force : Force the operation to run without confirmation}';

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
        return $this->signature;
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
        if ($this->option('status')) {
            $this->showStatus();
            return self::SUCCESS;
        }

        if (!$this->confirmToProceed()) {
            $this->error('Operation cancelled.');
            return self::FAILURE;
        }

        $this->comment('Running Database Migrations');

        try {
            // Set the output on the manager for this command execution. This allows the
            // manager to be constructed without a dependency on the console IO,
            // making it more reusable.
            $this->manager->setOutput($this->io);

            $migrationPaths = $this->getMigrationPaths();

            if (empty($migrationPaths)) {
                $this->info('No migration paths have been registered. Nothing to migrate.');
                return self::SUCCESS;
            }

            if ($this->option('refresh')) {
                $this->io->info('Refreshing migrations: Rolling back all migrations and running them again.');
                $this->manager->reset($migrationPaths);
                $this->manager->run($migrationPaths);
            } elseif ($this->option('rollback')) {
                $this->manager->rollback($migrationPaths);
            } else {
                $this->manager->run($migrationPaths);
            }
        } catch (\Throwable $e) {
            $this->error('An error occurred during migration: ' . $e->getMessage());
            if ($this->io->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }

        $this->info('Migration process completed.');
        return self::SUCCESS;
    }

    /**
     * Gathers all migration paths from the main application and all enabled modules.
     *
     * @return array
     */
    private function getMigrationPaths(): array
    {
        // Bắt đầu với các đường dẫn được định nghĩa trong file config/database.php
        $paths = $this->app->make('config')->get('database.migrations.paths', []);

        // Thêm đường dẫn migration từ tất cả các module đang được bật
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));
        foreach ($moduleJsonPaths as $path) {
            $moduleData = json_decode(file_get_contents($path), true);
            if (!empty($moduleData['enabled']) && $moduleData['enabled'] === true) {
                $moduleMigrationPath = dirname($path) . '/Infrastructure/Migrations';
                $paths[] = $moduleMigrationPath;
            }
        }

        // Luôn thêm đường dẫn migration gốc 'database/migrations' nếu nó tồn tại
        $paths[] = $this->app->basePath('database/migrations');

        // Lọc ra các đường dẫn không tồn tại và loại bỏ các giá trị trùng lặp
        return array_unique(array_filter($paths, 'is_dir'));
    }

    /**
     * Displays the status of all migrations.
     */
    private function showStatus(): void
    {
        $ran = $this->manager->getRan();
        $this->io->section('Ran Migrations:');
        if (empty($ran)) {
            $this->io->info('No migrations have been run.');
        } else {
            $this->io->listing($ran);
        }
    }

    /**
     * Get confirmation to proceed with the command in a production environment.
     *
     * @return bool
     */
    protected function confirmToProceed(): bool
    {
        // The --force flag should bypass the confirmation prompt.
        // Also, we don't need to prompt in non-production environments.
        if ($this->option('force') || ($this->app && config('app.env') !== 'production')) {
            return true;
        }

        // If we are in production and --force is not used, ask for confirmation.
        return $this->confirm('Do you really wish to run this command?');
    }
}
