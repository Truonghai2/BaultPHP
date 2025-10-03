<?php

namespace App\Providers;

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

            // Nếu debug được bật và debugbar tồn tại, hãy bọc PDO lại
            if ((bool) config('app.debug', false) && $app->bound('debugbar')) {
                /** @var \DebugBar\DebugBar $debugbar */
                $debugbar = $app->make('debugbar');
                /** @var \DebugBar\DataCollector\PDO\PDOCollector $pdoCollector */
                $pdoCollector = $debugbar->getCollector('pdo');

                $traceablePdo = new \DebugBar\DataCollector\PDO\TraceablePDO($pdo);
                $pdoCollector->addConnection($traceablePdo, $connection->getDefaultConnection());
                return $traceablePdo;
            }
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
    }
}
