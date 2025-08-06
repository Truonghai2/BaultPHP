<?php

namespace App\Providers;

use Core\ORM\Connection;
use Core\ORM\MigrationManager;
use Core\Support\ServiceProvider;
use PDO;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This is the perfect place to register paths for migrations, seeders, etc.
     *
     * @return void
     */
    public function register(): void
    {
        // Đăng ký PDO instance như một singleton.
        // Logic này nên được tập trung ở đây thay vì trong một phương thức static.
        // Điều này giúp DI container quản lý hoàn toàn vòng đời của PDO.
        $this->app->singleton(PDO::class, function ($app) {
            // Lấy ra đối tượng config từ container
            $config = $app->make('config');

            // Lấy tên kết nối mặc định (ví dụ: 'mysql') từ file config/database.php
            $connectionName = $config->get('database.default', 'mysql');

            // Lấy toàn bộ cấu hình cho kết nối mặc định đó
            $connectionConfig = $config->get("database.connections.{$connectionName}");

            if (!$connectionConfig) {
                throw new \InvalidArgumentException("Database connection [{$connectionName}] is not configured.");
            }

            // Lấy host từ cấu hình 'write' nếu có, nếu không thì lấy host chung.
            $host = $connectionConfig['write']['host'] ?? $connectionConfig['host'];

            // Tạo chuỗi DSN (Data Source Name)
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $connectionConfig['driver'],
                $host,
                $connectionConfig['port'],
                $connectionConfig['database'],
                $connectionConfig['charset']
            );

            try {
                return new PDO($dsn, $connectionConfig['username'], $connectionConfig['password'], $connectionConfig['options'] ?? []);
            } catch (\PDOException $e) {
                throw new \RuntimeException("Could not connect to the database [{$connectionName}]. Please check your configuration. Error: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        });

        // The MigrationManager now automatically detects the default migration path.
        // This provider is the perfect place to register database-related services.

        $this->app->singleton(MigrationManager::class, function ($app) {
            // Bây giờ chúng ta có thể resolve PDO trực tiếp từ container.
            $config = $app->make('config');
            $pdo = $app->make(PDO::class);
            $table = $config->get('database.migrations.table', 'migrations');
            return new MigrationManager($pdo, $table);
        });
    }
}
