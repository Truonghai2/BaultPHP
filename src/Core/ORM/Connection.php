<?php

namespace Core\ORM;

use PDO;
use PDOException;

/**
 * Connection manages database connections using a connection pool.
 * It supports multiple database configurations and handles connection creation.
 */
class Connection
{
    /**
     * @var array<string, PDO>
     */
    protected static array $pool = [];

    /**
     * Get a PDO connection instance.
     *
     * @param string|null $name
     * @return PDO
     * @throws \Exception
     */
    public static function get(string $name = null, string $type = 'write'): PDO
    {
        $name ??= config('database.default', 'mysql');
        $poolKey = "{$name}::{$type}";

        if (isset(static::$pool[$poolKey])) {
            return static::$pool[$poolKey];
        }

        $connections = config('database.connections');
        $originalConfig = $connections[$name] ?? null;

        if (!$originalConfig) {
            throw new \Exception("Database connection [$name] not configured.");
        }

        // Bắt đầu với một bản sao sạch của config cho lần resolve này
        $config = $originalConfig;

        // Xử lý tách biệt read/write
        if (isset($config['read']) && isset($config['write'])) {
            if (!in_array($type, ['read', 'write'])) {
                throw new \InvalidArgumentException("Loại kết nối không hợp lệ [{$type}]. Phải là 'read' hoặc 'write'.");
            }

            // Hợp nhất config cơ sở với config của loại kết nối cụ thể.
            // Config cụ thể (ví dụ: 'host') sẽ ghi đè lên config cơ sở.
            $typeSpecificConfig = $config[$type];
            unset($config['read'], $config['write']);
            $config = array_merge($config, $typeSpecificConfig);
        }

        $driver = $config['driver'];
        $dbname = $config['database'];

        try {
            $dsn = self::makeDsn($config);

            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
            ];
            $options = ($config['options'] ?? []) + $defaultOptions;

            $pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options,
            );

            static::$pool[$poolKey] = $pdo;
        } catch (PDOException $e) {
            if (
                $driver === 'mysql' &&
                str_contains($e->getMessage(), 'Unknown database')
            ) {
                echo "Database `$dbname` không tồn tại.\n";
                echo 'Bạn có muốn tạo mới không? (y/n): ';
                $answer = strtolower(trim(fgets(STDIN)));

                if ($answer === 'y') {
                    // Luôn sử dụng config 'write' để tạo database
                    $writeConfig = $originalConfig;
                    if (isset($writeConfig['write'])) {
                        $typeSpecificConfig = $writeConfig['write'];
                        unset($writeConfig['read'], $writeConfig['write']);
                        $writeConfig = array_merge($writeConfig, $typeSpecificConfig);
                    }

                    self::createDatabase($writeConfig);
                    return self::get($name, $type); // Gọi lại với type ban đầu
                }

                throw new \Exception('Quá trình bị huỷ. Cơ sở dữ liệu chưa được tạo.');
            }

            throw $e;
        }

        return static::$pool[$poolKey];
    }

    /**
     * Close all connections in the pool.
     */
    public static function flush(): void
    {
        static::$pool = [];
    }

    /**
     * Create a DSN string based on the database configuration.
     *
     * @param array $config
     * @return string
     * @throws \Exception
     */
    protected static function makeDsn(array $config): string
    {
        $driver = $config['driver'];

        return match ($driver) {
            'mysql', 'pgsql' => sprintf(
                '%s:host=%s;port=%s;dbname=%s%s',
                $driver,
                $config['host'],
                $config['port'] ?? ($driver === 'mysql' ? 3306 : 5432),
                $config['database'],
                ($driver === 'mysql' ? ';charset=' . ($config['charset'] ?? 'utf8mb4') : '')
            ),
            'sqlite' => 'sqlite:' . $config['database'],
            default => throw new \Exception("Unsupported driver [{$driver}]"),
        };
    }

    /**
     * Create a new database if it does not exist.
     *
     * @param array $config
     * @throws \Exception
     */
    protected static function createDatabase(array $config): void
    {
        // Đảm bảo rằng chúng ta có cấu hình host, vì nó rất quan trọng để kết nối.
        if (empty($config['host'])) {
            throw new \InvalidArgumentException('Database host is not configured for creation.');
        }

        // Xây dựng DSN mà không chỉ định tên database, vì nó chưa tồn tại.
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $dbName = $config['database'];
            $charset = $config['charset'] ?? 'utf8mb4';
            $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';

            // Sử dụng prepared statements để tăng cường bảo mật và tránh SQL injection.
            $stmt = $pdo->prepare("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "``", $dbName) . "` CHARACTER SET ? COLLATE ?");
            $stmt->execute([$charset, $collation]);

            echo "Database `{$dbName}` đã được tạo thành công.\n";
        } catch (PDOException $e) {
            // Cung cấp thông báo lỗi chi tiết hơn để dễ dàng gỡ lỗi.
            throw new \RuntimeException("Không thể tạo database: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}