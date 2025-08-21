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
        $this->app->singleton(\PDO::class, function ($app) {
            // Tham số thứ hai 'write' là mặc định trong Connection::connection()
            // nhưng được ghi rõ ở đây để tường minh.
            return $app->make(Connection::class)->connection(null, 'write');
        });

        // Đăng ký kết nối ĐỌC (read).
        // Connection::connection() đã chứa logic để tự động fallback về kết nối 'write'
        // nếu một read replica riêng biệt không được cấu hình.
        $this->app->singleton('pdo.read', function ($app) {
            return $app->make(Connection::class)->connection(null, 'read');
        });

        // Register the Schema utility so it can be injected.
        $this->app->singleton(\Core\Schema\Schema::class, function ($app) {
            return new \Core\Schema\Schema($app->make(\PDO::class));
        });

        // The MigrationManager now automatically detects the default migration path.
        // This provider is the perfect place to register database-related services.

        $this->app->singleton(MigrationManager::class, function ($app) {
            // Bây giờ chúng ta có thể resolve PDO trực tiếp từ container.
            $config = $app->make('config');
            $pdo = $app->make(\PDO::class);
            $schema = $app->make(\Core\Schema\Schema::class);
            $table = $config->get('database.migrations.table', 'migrations');
            return new MigrationManager($pdo, $schema, $table);
        });
    }
}
