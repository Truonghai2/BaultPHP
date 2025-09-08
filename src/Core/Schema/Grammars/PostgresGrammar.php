<?php

namespace Core\Schema\Grammars;

use Core\Schema\Blueprint;
use Core\Schema\ColumnDefinition;
use Core\Support\Fluent;

class PostgresGrammar extends MySqlGrammar
{
    public function compileCreate(Blueprint $blueprint): array
    {
        $columns = $this->compileColumns($blueprint);
        $constraints = $this->compileConstraints($blueprint);

        $sql = "CREATE TABLE {$this->quote($blueprint->getTableName())} ("
            . implode(', ', array_merge($columns, $constraints))
            . ')';

        return [$sql];
    }

    protected function compileConstraints(Blueprint $blueprint): array
    {
        $constraints = [];
        foreach ($blueprint->getColumns() as $column) {
            if ($column->foreign) {
                $constraints[] = $this->compileForeignKey($blueprint, $column);
            }
        }
        return $constraints;
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

        return $sql;
    }

    public function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'BIGSERIAL PRIMARY KEY';
    }

    public function typeIncrements(ColumnDefinition $column): string
    {
        return 'SERIAL PRIMARY KEY';
    }

    public function typeUnsignedBigInteger(ColumnDefinition $column): string
    {
        return 'BIGINT';
    }

    public function typeUnsignedInteger(ColumnDefinition $column): string
    {
        return 'INT';
    }

    public function typeUnsignedTinyInteger(ColumnDefinition $column): string
    {
        return 'SMALLINT';
    }

    public function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'SMALLINT';
    }

    public function typeBoolean(ColumnDefinition $column): string
    {
        return 'BOOLEAN';
    }

    public function typeTimestamp(ColumnDefinition $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    public function typeDatetime(ColumnDefinition $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    public function typeJson(ColumnDefinition $column): string
    {
        return 'JSONB';
    }

    public function typeUuid(ColumnDefinition $column): string
    {
        return 'UUID';
    }

    public function typeLongText(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    public function typeEnum(ColumnDefinition $column): string
    {
        $allowed = implode(', ', array_map(fn ($val) => "'" . str_replace("'", "''", $val) . "'", $column->allowed));
        return "TEXT CHECK ({$this->quote($column->name)} IN ({$allowed}))";
    }

    protected function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);
        $columns = implode(', ', array_map([$this, 'quote'], $command->columns));

        return "ALTER TABLE {$table} ADD CONSTRAINT {$index} UNIQUE ({$columns})";
    }

    protected function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);
        $columns = implode(', ', array_map([$this, 'quote'], $command->columns));

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
        return "DROP INDEX {$this->quote($command->name)}";
    }

    protected function compileForeignKey(Blueprint $blueprint, ColumnDefinition $column): string
    {
        $onDelete = isset($column->foreign['onDelete']) ? ' ON DELETE ' . strtoupper($column->foreign['onDelete']) : '';
        $onUpdate = isset($column->foreign['onUpdate']) ? ' ON UPDATE ' . strtoupper($column->foreign['onUpdate']) : '';

        $constraintName = $this->quote($blueprint->getTableName() . '_' . $column->name . '_foreign');

        return "CONSTRAINT {$constraintName} FOREIGN KEY ({$this->quote($column->name)}) "
            . "REFERENCES {$this->quote($column->foreign['table'])} ({$this->quote($column->foreign['column'])}){$onDelete}{$onUpdate}";
    }

    /**
     * Biên dịch một lệnh thay đổi cột thành câu truy vấn cho PostgreSQL.
     *
     * @param  \Core\Schema\Blueprint  $blueprint
     * @param  \Core\Schema\ColumnDefinition  $column
     * @return array
     */
    protected function compileChange(Blueprint $blueprint, ColumnDefinition $column): string
    {
        $table = $this->quote($blueprint->getTableName());
        $colName = $this->quote($column->name);
        $type = $this->getType($column);

        $statements = [];

        $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} TYPE {$type} USING {$colName}::{$type}";

        if (isset($column->default)) {
            $default = $this->formatDefault($column->default);
            $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} SET DEFAULT {$default}";
        }

        if ($column->nullable) {
            $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} DROP NOT NULL";
        } else {
            $statements[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} SET NOT NULL";
        }

        return (string)$statements;
    }
}
