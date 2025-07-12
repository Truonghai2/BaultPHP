<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\ORM\Migration\Migration;
use PDO;

class CreateProductsTable implements Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            -- SQL to create table goes here
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("
            -- SQL to drop table goes here
        ");
    }
}