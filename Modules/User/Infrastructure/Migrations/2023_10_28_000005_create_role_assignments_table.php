<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\ORM\Migration\Migration;
use PDO;

class CreateRoleAssignmentsTable implements Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                user_id INT NOT NULL,
                context_id INT NOT NULL,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (context_id) REFERENCES contexts(id) ON DELETE CASCADE,
                UNIQUE KEY (role_id, user_id, context_id)
            )
        ");
    }

    public void down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS role_assignments");
    }
}