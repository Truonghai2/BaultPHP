<?php

namespace Core\Schema\Grammars;

use Core\Schema\Blueprint;
use Core\Schema\ColumnDefinition;
use Core\Support\Fluent;

class SqlServerGrammar extends PostgresGrammar
{
    // NOTE: This class extends PostgresGrammar which extends MySqlGrammar.
    // This inheritance chain is not ideal. A better approach would be to have a shared
    // abstract BaseGrammar and have each dialect-specific grammar extend that.

    public function quote(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    public function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'BIGINT IDENTITY(1,1) PRIMARY KEY';
    }

    public function typeIncrements(ColumnDefinition $column): string
    {
        return 'INT IDENTITY(1,1) PRIMARY KEY';
    }

    public function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'TINYINT';
    }

    public function typeString(ColumnDefinition $column): string
    {
        return "NVARCHAR({$column->length})";
    }

    public function typeText(ColumnDefinition $column): string
    {
        return 'NVARCHAR(MAX)';
    }

    public function typeLongText(ColumnDefinition $column): string
    {
        return 'NVARCHAR(MAX)';
    }

    public function typeJson(ColumnDefinition $column): string
    {
        return 'NVARCHAR(MAX)';
    }

    public function typeUuid(ColumnDefinition $column): string
    {
        return 'UNIQUEIDENTIFIER';
    }

    protected function addModifiers(string $sql, Blueprint $blueprint, ColumnDefinition $column): string
    {
        if ($column->nullable) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if (isset($column->default)) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column->default);
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        // SQL Server does not support inline column comments.
        // They must be added with a separate sp_addextendedproperty call.
        return $sql;
    }

    protected function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        $columns = implode(', ', array_map([$this, 'quote'], $command->columns));
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);

        return "ALTER TABLE {$table} ADD CONSTRAINT {$index} UNIQUE ({$columns})";
    }

    protected function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        $columns = implode(', ', array_map([$this, 'quote'], $command->columns));
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);

        return "CREATE INDEX {$index} ON {$table} ({$columns})";
    }

    protected function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);

        return "ALTER TABLE {$table} DROP CONSTRAINT {$index}";
    }

    protected function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);

        return "DROP INDEX {$index} ON {$table}";
    }

    /**
     * Biên dịch một lệnh đổi tên cột thành câu truy vấn cho SQL Server.
     *
     * @param  \Core\Schema\Blueprint  $blueprint
     * @param  \Core\Support\Fluent  $command
     * @return string
     */
    protected function compileRenameColumn(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->quote($blueprint->getTableName());

        return "EXEC sp_rename N'{$table}.{$this->quote($command->from)}', N'{$this->quote($command->to)}', 'COLUMN'";
    }
}
