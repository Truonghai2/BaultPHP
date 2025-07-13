<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use PDO;
use RuntimeException;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the database services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(PDO::class, function ($app) {
            /** @var \Core\Config $config */
            $config = $app->make('config');

            $default = $config->get('database.default');
            if (!$default) {
                throw new RuntimeException('Default database connection not specified in config/database.php.');
            }

            $connection = $config->get("database.connections.{$default}");
            if (!$connection) {
                throw new RuntimeException("Database connection [{$default}] not configured.");
            }

            // Build DSN string. Example: mysql:host=127.0.0.1;port=3306;dbname=bault;charset=utf8mb4
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['driver'],
                $connection['host'],
                $connection['port'] ?? '3306', // Default port for mysql
                $connection['database'],
                $connection['charset'] ?? 'utf8mb4'
            );

            $options = $connection['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            return new PDO(
                $dsn,
                $connection['username'],
                $connection['password'],
                $options
            );
        });
    }
}