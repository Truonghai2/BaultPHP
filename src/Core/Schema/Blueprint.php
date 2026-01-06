<?php

namespace Core\Schema;

use Core\Schema\Grammars\Grammar;

class Blueprint
{
    protected string $table;
    protected Grammar $grammar;

    /**
     * The commands that should be run for the table.
     *
     * @var array
     */
    protected array $commands = [];

    /**
     * The columns that should be added to the table.
     *
     * @var ColumnDefinition[]
     */
    protected array $columns = [];

    public function __construct(string $table, Grammar $grammar)
    {
        $this->table = $table;
        $this->grammar = $grammar;
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     * This is the recommended primary key type.
     */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($name);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     */
    public function increments(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn('increments', $name);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function bigIncrements(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn('bigIncrements', $name);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     */
    public function unsignedBigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('unsignedBigInteger', $name);
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $name);
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $name, ['length' => $length]);
    }

    /**
     * Create a new text column on the table.
     */
    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn('text', $name);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     */
    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn('integer', $name);
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     */
    public function unsignedInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('unsignedInteger', $name);
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     */
    public function tinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $name);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     */
    public function unsignedTinyInteger(string $name): ColumnDefinition
    {
        return $this->addColumn('unsignedTinyInteger', $name);
    }

    /**
     * Create a new timestamp column on the table.
     */
    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn('timestamp', $name)->nullable()->default(null);
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
        $this->addCommand('primary', $columns);
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

    public function dropUnique(string|array $columns, ?string $name = null): void
    {
        $this->addCommand('dropUnique', $columns, $name);
    }

    public function dropIndex(string|array $columns, ?string $name = null): void
    {
        $this->addCommand('dropIndex', $columns, $name);
    }

    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $attributes = array_merge(compact('type', 'name'), $parameters);
        $column = new ColumnDefinition($attributes);
        $this->columns[] = $column;
        return $column;
    }

    /**
     * Drop one or more columns from the table.
     *
     * @param  string|array  $columns
     * @return void
     */
    public function dropColumn(string|array $columns): void
    {
        $this->addCommand('dropColumn', (array) $columns);
    }

    public function renameColumn(string $oldName, string $newName): void
    {
        $this->addCommand('renameColumn', ['from' => $oldName, 'to' => $newName]);
    }

    public function change(string $columnName, string $newType, array $options = []): ColumnDefinition
    {
        $column = $this->addColumn($newType, $columnName, $options);
        $column->change();
        return $column;
    }

    public function dropPrimaryKey(): void
    {
        $this->addCommand('dropPrimary', []);
    }

    public function dropForeignKey(string $column): void
    {
        $this->addCommand('dropForeign', (array) $column);
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn('boolean', $name);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $name, compact('precision', 'scale'));
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn('date', $name);
    }

    public function time(string $name): ColumnDefinition
    {
        return $this->addColumn('time', $name);
    }

    public function datetime(string $name): ColumnDefinition
    {
        return $this->addColumn('datetime', $name);
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn('json', $name);
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $name, ['allowed' => $values]);
    }

    public function set(string $name, array $values): ColumnDefinition
    {
        return $this->addColumn('set', $name, ['allowed' => $values]);
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->addColumn('uuid', $name);
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn('longText', $name);
    }

    /**
     * Get the table name for the blueprint.
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Get all of the commands on the blueprint.
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
