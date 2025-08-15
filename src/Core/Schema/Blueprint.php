<?php

namespace Core\Schema;

class Blueprint
{
    protected string $table;
    /**
     * The commands that should be run for the table.
     *
     * @var array
     */
    protected array $commands = [];
    protected array $columns = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     * This is the recommended primary key type.
     *
     * @param  string  $name
     * @return void
     */
    public function id(string $name = 'id'): void
    {
        $this->bigIncrements($name);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param  string  $name
     * @return void
     */
    public function increments(string $name = 'id'): void
    {
        $this->columns[$name] = new ColumnDefinition("`$name` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY");
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param  string  $name
     * @return void
     */
    public function bigIncrements(string $name = 'id'): void
    {
        $this->columns[$name] = new ColumnDefinition("`$name` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY");
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` BIGINT UNSIGNED");
        $this->columns[$name] = $col;
        return $col;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` VARCHAR($length)");
        $this->columns[$name] = $col;
        return $col;
    }

    /**
     * Create a new text column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function text(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` TEXT");
        $this->columns[$name] = $col;
        return $col;
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function integer(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` INT");
        $this->columns[$name] = $col;
        return $col;
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function unsignedInteger(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` INT UNSIGNED");
        $this->columns[$name] = $col;
        return $col;
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function tinyInteger(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` TINYINT");
        $this->columns[$name] = $col;
        return $col;
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function unsignedTinyInteger(string $name): ColumnDefinition
    {
        $col = new ColumnDefinition("`$name` TINYINT UNSIGNED");
        $this->columns[$name] = $col;
        return $col;
    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param  string  $name
     * @return \Core\Schema\ColumnDefinition
     */
    public function timestamp(string $name): ColumnDefinition
    {
        // We create a base definition and then chain the modifiers
        // to make it consistent with other column types.
        $col = new ColumnDefinition("`$name` TIMESTAMP");
        $this->columns[$name] = $col;
        return $col->nullable()->default(null);
    }

    /**
     * Add created_at and updated_at timestamps to the table.
     *
     * @return void
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
    }

    /**
     * Add soft delete timestamps to the table.
     *
     * @return void
     */
    public function softDeletes(): void
    {
        $this->timestamp('deleted_at');
    }

    /**
     * Get the SQL statement to create the table.
     *
     * @return string
     */
    public function getCreateSql(): string
    {
        $columnsSql = [];
        $foreignKeys = [];

        foreach ($this->columns as $name => $col) {
            $columnsSql[] = $col->getSql();

            if ($col->isUnique) {
                $this->unique($name);
            }
            if ($col->isIndex) {
                $this->index($name);
            }
            if ($foreignKey = $col->getForeignKey()) {
                $foreignKeys[] = $foreignKey;
            }
        }

        $commandsSql = [];
        foreach ($this->commands as $command) {
            $columns = implode(', ', array_map(fn ($c) => "`$c`", $command['columns']));

            switch (strtoupper($command['type'])) {
                case 'PRIMARY':
                    $commandsSql[] = "PRIMARY KEY ({$columns})";
                    break;

                case 'UNIQUE':
                case 'INDEX':
                    $constraintName = $command['name'];
                    $type = strtoupper($command['type']);
                    $commandsSql[] = "{$type} `{$constraintName}` ({$columns})";
                    break;
            }
        }

        $sqlParts = array_merge($columnsSql, $commandsSql, $foreignKeys);

        return "CREATE TABLE `{$this->table}` (\n  " . implode(",\n  ", $sqlParts) . "\n)";
    }

    /**
     * Get the SQL statement to drop the table.
     *
     * @return string
     */
    public function getDropSql(): string
    {
        return "DROP TABLE IF EXISTS `{$this->table}`";
    }

    /**
     * Specify a unique index for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @return void
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->addCommand('unique', $columns, $name);
    }

    /**
     * Specify an index for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @return void
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $this->addCommand('index', $columns, $name);
    }

    /**
     * Specify the primary key for the table.
     *
     * @param  string|array  $columns
     * @return void
     */
    public function primary(string|array $columns): void
    {
        $this->addPrimaryKey((array) $columns);
    }

    public function foreign(string $column): ColumnDefinition
    {
        return $this->columns[$column];
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->unsignedBigInteger($name);
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

    /**
     * Add a new command to the blueprint.
     *
     * @param  string  $type
     * @param  string|array  $columns
     * @param  string|null  $name
     * @return void
     */
    protected function addCommand(string $type, string|array $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_' . $type;

        $this->commands[] = [
            'type'    => $type,
            'columns' => $columns,
            'name'    => $name,
        ];
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
        $this->addCommand('primary', $columns);
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
