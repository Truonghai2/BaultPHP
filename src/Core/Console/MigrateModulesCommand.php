<?php

namespace Core\Console;

use Core\ORM\Connection;
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
        parent::__construct();
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

        try {
            $config = $this->app->make('config');
            $migrationPaths = $config->get('database.migrations.paths', []);

            if (empty($migrationPaths)) {
                $this->io->info("No migration paths have been registered. Nothing to migrate.");
                return;
            }

            // Lấy kết nối PDO mặc định từ container
            $pdo = Connection::get($config->get('database.default'));

            // Tạo manager, truyền vào kết nối PDO và đối tượng IO
            $manager = new MigrationManager($pdo, $this->io);

            if ($status) {
                $ran = $manager->getRan();
                $this->io->section('Ran Migrations:');
                if (empty($ran)) {
                    $this->io->info('No migrations have been run.');
                } else {
                    $this->io->listing($ran);
                }
            } elseif ($rollback) {
                $manager->rollback($migrationPaths);
            } elseif ($refresh) {
                $this->io->warning("Refresh not implemented yet.");
            } else {
                $manager->run($migrationPaths);
            }
        } catch (\Throwable $e) {
            $this->io->error("An error occurred during migration: " . $e->getMessage());
            return;
        }

        $this->io->success("Migration process completed.");
    }
}