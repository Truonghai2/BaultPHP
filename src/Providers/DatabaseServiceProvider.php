<?php

namespace App\Providers;

use Core\Database\GraphDatabase;
use Core\Database\TimeSeriesDatabase;
use Core\Database\VectorDatabase;
use Core\ORM\Connection;
use Core\ORM\MigrationManager;
use Core\Support\ServiceProvider;

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
        // Đăng ký ORM Connection manager như một singleton.
        // Class này sẽ chịu trách nhiệm quản lý các kết nối CSDL,
        // bao gồm cả việc sử dụng connection pool trong môi trường Swoole.
        $this->app->singleton(Connection::class);

        // Đăng ký kết nối GHI (write) mặc định bằng cách ủy quyền cho Connection manager.
        // Bất cứ khi nào có yêu cầu cho một instance \PDO, container sẽ nhờ Connection
        // class để cung cấp kết nối 'write' mặc định.
        // Connection class sẽ tự quyết định lấy kết nối từ pool (Swoole) hay tạo mới (CLI).
        $this->app->singleton(\PDO::class, function ($app) { // This is the default 'write' connection
            $connection = $app->make(Connection::class);
            $pdo = $connection->connection(null, 'write');

            return $pdo;
        });

        $this->app->singleton('pdo.read', function ($app) {
            return $app->make(Connection::class)->connection(null, 'read');
        });

        $this->app->singleton(\Core\Schema\Schema::class, function ($app) {
            return new \Core\Schema\Schema($app->make(\PDO::class));
        });

        $this->app->singleton(MigrationManager::class, function ($app) {
            $config = $app->make('config');
            $pdo = $app->make(\PDO::class);
            $schema = $app->make(\Core\Schema\Schema::class);
            $table = $config->get('database.migrations.table', 'migrations');
            return new MigrationManager($pdo, $schema, $table);
        });

        $this->registerAdvancedDatabases();
    }

    /**
     * Register advanced database technologies
     */
    protected function registerAdvancedDatabases(): void
    {
        $config = $this->app->make('config');
        $advancedConfig = $config->get('database-advanced', []);

        // Register Vector Database
        if ($advancedConfig['vector']['enabled'] ?? false) {
            $this->app->singleton(VectorDatabase::class, function ($app) use ($advancedConfig) {
                return new VectorDatabase($advancedConfig['vector'] ?? []);
            });
        }

        // Register Time-Series Database
        if ($advancedConfig['timeseries']['enabled'] ?? false) {
            $this->app->singleton(TimeSeriesDatabase::class, function ($app) use ($advancedConfig) {
                return new TimeSeriesDatabase($advancedConfig['timeseries'] ?? []);
            });
        }

        // Register Graph Database
        if ($advancedConfig['graph']['enabled'] ?? false) {
            $this->app->singleton(GraphDatabase::class, function ($app) use ($advancedConfig) {
                return new GraphDatabase($advancedConfig['graph'] ?? []);
            });
        }
    }
}
