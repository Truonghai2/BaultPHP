<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\ORM\Migration\Migration;
use PDO;

class CreateContextsTable implements Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contexts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT UNSIGNED NULL,
                context_level VARCHAR(100) NOT NULL COMMENT 'e.g., system, course, post',
                instance_id INT UNSIGNED NOT NULL,
                path VARCHAR(255) NOT NULL,
                depth TINYINT NOT NULL,
                FOREIGN KEY (parent_id) REFERENCES contexts(id) ON DELETE CASCADE
            )
        ");

        // Tạo context gốc cho toàn hệ thống
        $pdo->exec("
            INSERT INTO contexts (id, parent_id, context_level, instance_id, path, depth)
            VALUES (1, NULL, 'system', 1, '1/', 1)
            ON DUPLICATE KEY UPDATE id=id;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS contexts");
    }
}