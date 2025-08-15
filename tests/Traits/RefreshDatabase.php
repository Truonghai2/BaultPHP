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
        // Lấy tên kết nối mặc định từ config, fallback về 'sqlite' cho môi trường test
        // Giá trị này được định nghĩa trong phpunit.xml
        $config = $this->app->make('config');
        $connectionName = $config->get('database.default', 'sqlite');

        // Lấy kết nối CSDL cho môi trường test (SQLite in-memory)
        $pdo = Connection::get($connectionName);

        // Lấy tất cả các đường dẫn và tên bảng migration đã đăng ký từ config
        $migrationPaths = $config->get('database.migrations.paths', []);
        $migrationTable = $config->get('database.migrations.table', 'migrations');

        // Khởi tạo MigrationManager (không cần output ra console trong test)
        $manager = new MigrationManager($pdo, $migrationTable);

        $manager->run($migrationPaths);
    }
}
