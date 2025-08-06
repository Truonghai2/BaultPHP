<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Command;
use Core\CQRS\CommandMiddleware;
use Core\ORM\Connection;
use PDO;
use Throwable;

class DatabaseTransactionMiddleware implements CommandMiddleware
{
    private PDO $pdo;

    public function __construct()
    {
        // Lấy kết nối PDO mặc định từ Connection manager của framework.
        $this->pdo = Connection::get();
    }

    public function handle(Command $command, callable $next)
    {
        $this->pdo->beginTransaction();

        try {
            $result = $next($command);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            // Ném lại exception để lớp gọi bên ngoài vẫn biết có lỗi xảy ra.
            throw $e;
        }
    }
}
