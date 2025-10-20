<?php

namespace Tests\Traits;

use Core\ORM\Connection;
use Core\ORM\MigrationManager;

trait RefreshDatabase
{
    /**
     * Boots the trait.
     * This method is called by the base TestCase's setUp method.
     */
    protected function bootRefreshDatabase(): void
    {
        $config = $this->app->make('config');
        $connectionName = $config->get('database.default', 'sqlite');

        $pdo = Connection::get($connectionName);

        $migrationPaths = $config->get('database.migrations.paths', []);
        $migrationTable = $config->get('database.migrations.table', 'migrations');

        $manager = new MigrationManager($pdo, $migrationTable);

        $manager->run($migrationPaths);
    }
}
