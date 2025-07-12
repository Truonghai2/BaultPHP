<?php

namespace Core\ORM\Migration;

use PDO;

interface Migration
{
    /**
     * Run the migrations.
     *
     * @param PDO $pdo
     * @return void
     */
    public function up(PDO $pdo): void;

    /**
     * Reverse the migrations.
     *
     * @param PDO $pdo
     * @return void
     */
    public function down(PDO $pdo): void;
}