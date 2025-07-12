<?php

namespace Core\Schema;

use Core\ORM\Connection;

abstract class Migration
{
    protected \PDO $pdo;
    protected Schema $schema;

    public function __construct()
    {
        $this->pdo = Connection::get();
        $this->schema = new Schema($this->pdo);
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
