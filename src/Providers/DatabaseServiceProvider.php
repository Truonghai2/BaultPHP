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
        // Register the Connection manager as a singleton.
        $this->app->singleton(Connection::class, function ($app) {
            return new Connection($app);
        });

        // Đăng ký kết nối GHI (write) mặc định bằng cách ủy quyền cho Connection manager.
        // Điều này tập trung hóa logic kết nối, bao gồm cả việc xử lý connection pool
        // trong môi trường Swoole, đảm bảo tính nhất quán trên toàn ứng dụng.
        $this->app->singleton(\PDO::class, function ($app) {
            // Tham số thứ hai 'write' là mặc định trong Connection::get() nhưng được ghi rõ ở đây.
            return $app->make(Connection::class)->connection(null, 'write');
        });

        // Đăng ký kết nối ĐỌC (read).
        // Connection::get() đã chứa logic để tự động fallback về kết nối 'write'
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
