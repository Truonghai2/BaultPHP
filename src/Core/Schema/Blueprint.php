<?php

namespace Core\Schema;

class Blueprint
{
    protected string $table;
    protected array $columns = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(string $name = 'id'): void
    {
        $this->columns[$name] = new ColumnDefinition("`$name` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY");
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` VARCHAR($length)");
        $this->columns[$name] = $col;
        return $col;
    }

    public function text(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` TEXT");
        $this->columns[$name] = $col;
        return $col;
    }

    public function integer(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` INT");
        $this->columns[$name] = $col;
        return $col;
    }

    public function timestamp(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` TIMESTAMP NULL DEFAULT NULL");
        $this->columns[$name] = $col;
        return $col;
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
    }

    public function softDeletes(): void
    {
        $this->timestamp('deleted_at');
    }

    public function getCreateSql(): string
    {
        $parts = [];

        foreach ($this->columns as $name => $col) {
            foreach ($col->getSql($name) as $line) {
                $parts[] = $line;
            }
        }

        return "CREATE TABLE `{$this->table}` (\n  " . implode(",\n  ", $parts) . "\n)";
    }

    public function getDropSql(): string
    {
        return "DROP TABLE IF EXISTS `{$this->table}`";
    }
}
