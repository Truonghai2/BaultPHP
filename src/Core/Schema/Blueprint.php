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
        // We create a base definition and then chain the modifiers
        // to make it consistent with other column types.
        $col = new ColumnDefinition("`$name` TIMESTAMP");
        $this->columns[$name] = $col;
        return $col->nullable()->default(null);
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

    public function unique(string $column): void
    {
        if (isset($this->columns[$column])) {
            $this->columns[$column]->setUnique();
        }
    }

    public function foreign(string $column, string $references, string $onDelete = 'CASCADE'): void
    {
        if (isset($this->columns[$column])) {
            $this->columns[$column]->setForeignKey($references, $onDelete);
        }
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    public function __toString(): string
    {
        return $this->getCreateSql();
    }

    public function createIndex(string $name, array $columns): void
    {
        // This method can be used to create an index on the table.
        // Implementation can be added as needed.
    }

    public function dropIndex(string $name): void
    {
        // This method can be used to drop an index from the table.
        // Implementation can be added as needed.
    }

    public function addColumn(string $type, string $name, array $options = []): void
    {
        // This method can be used to add a custom column type.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` $type");
        foreach ($options as $key => $value) {
            $col->setOption($key, $value);
        }
        $this->columns[$name] = $col;
    }

    public function dropColumn(string $name): void
    {
        // This method can be used to drop a column from the table.
        // Implementation can be added as needed.
        unset($this->columns[$name]);
    }

    public function renameColumn(string $oldName, string $newName): void
    {
        // This method can be used to rename a column in the table.
        // Implementation can be added as needed.
        if (isset($this->columns[$oldName])) {
            $this->columns[$newName] = $this->columns[$oldName];
            unset($this->columns[$oldName]);
            $this->columns[$newName]->setName($newName);
        }
    }

    public function changeColumn(string $name, string $type, array $options = []): void
    {
        // This method can be used to change the type or options of an existing column.
        // Implementation can be added as needed.
        if (isset($this->columns[$name])) {
            $col = new ColumnDefinition("`$name` $type");
            foreach ($options as $key => $value) {
                $col->setOption($key, $value);
            }
            $this->columns[$name] = $col;
        }
    }

    public function addPrimaryKey(array $columns): void
    {
        // This method can be used to add a primary key constraint.
        // Implementation can be added as needed.
        $this->columns['primary_key'] = new ColumnDefinition('PRIMARY KEY (' . implode(', ', array_map(fn($col) => "`$col`", $columns)) . ')');
    }

    public function dropPrimaryKey(): void
    {
        // This method can be used to drop the primary key constraint.
        // Implementation can be added as needed.
        unset($this->columns['primary_key']);
    }

    public function addForeignKey(string $column, string $references, string $onDelete = 'CASCADE'): void
    {
        // This method can be used to add a foreign key constraint.
        // Implementation can be added as needed.
        if (isset($this->columns[$column])) {
            $this->columns[$column]->setForeignKey($references, $onDelete);
        }
    }

    public function dropForeignKey(string $column): void
    {
        // This method can be used to drop a foreign key constraint.
        // Implementation can be added as needed.
        if (isset($this->columns[$column])) {
            $this->columns[$column]->dropForeignKey();
        }
    }

    public function boolean(string $name): ColumnDefinition
    {
        // This method can be used to add a boolean column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` BOOLEAN");
        $this->columns[$name] = $col;
        return $col;
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        // This method can be used to add a decimal column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` DECIMAL($precision, $scale)");
        $this->columns[$name] = $col;
        return $col;
    }

    public function date(string $name): ColumnDefinition
    {
        // This method can be used to add a date column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` DATE");
        $this->columns[$name] = $col;
        return $col;
    }

    public function time(string $name): ColumnDefinition
    {
        // This method can be used to add a time column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` TIME");
        $this->columns[$name] = $col;
        return $col;
    }

    public function datetime(string $name): ColumnDefinition
    {
        // This method can be used to add a datetime column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` DATETIME");
        $this->columns[$name] = $col;
        return $col;
    }

    public function json(string $name): ColumnDefinition
    {
        // This method can be used to add a JSON column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` JSON");
        $this->columns[$name] = $col;
        return $col;
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        // This method can be used to add an ENUM column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` ENUM('" . implode("', '", $values) . "')");
        $this->columns[$name] = $col;
        return $col;
    }

    public function set(string $name, array $values): ColumnDefinition
    {
        // This method can be used to add a SET column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` SET('" . implode("', '", $values) . "')");
        $this->columns[$name] = $col;
        return $col;
    }

    public function default(string $name, $value): void
    {
        // This method can be used to set a default value for a column.
        // Implementation can be added as needed.
        if (isset($this->columns[$name])) {
            $this->columns[$name]->setDefault($value);
        }
    }

    public function nullable(string $name): void
    {
        // This method can be used to set a column as nullable.
        // Implementation can be added as needed.
        if (isset($this->columns[$name])) {
            $this->columns[$name]->setNullable();
        }
    }

    public function unsigned(string $name): void
    {
        // This method can be used to set a column as unsigned.
        // Implementation can be added as needed.
        if (isset($this->columns[$name])) {
            $this->columns[$name]->setUnsigned();
        }
    }

    public function uuid(string $name): ColumnDefinition
    {
        // This method can be used to add a UUID column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` CHAR(36)");
        $this->columns[$name] = $col;
        return $col->default('uuid()');
    }

    public function longText(string $name): ColumnDefinition
    {
        // This method can be used to add a LONGTEXT column.
        // Implementation can be added as needed.
        $col = new ColumnDefinition("`$name` LONGTEXT");
        $this->columns[$name] = $col;
        return $col;
    }
}