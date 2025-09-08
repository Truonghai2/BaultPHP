<?php

namespace Core\Schema\Grammars;

use Core\Schema\Blueprint;
use Core\Schema\ColumnDefinition;
use Core\Support\Fluent;

class SQLiteGrammar extends PostgresGrammar
{
    // NOTE: This class extends PostgresGrammar. This inheritance is not ideal.
    // It should ideally extend a BaseGrammar to avoid inheriting incorrect SQL syntax.

    public function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    public function typeIncrements(ColumnDefinition $column): string
    {
        return 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    public function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'TINYINT';
    }

    public function typeJson(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    public function typeUuid(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    public function typeLongText(ColumnDefinition $column): string
    {
        return 'TEXT';
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

    protected function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Biên dịch một lệnh xóa cột thành câu truy vấn cho SQLite.
     * SQLite chỉ hỗ trợ xóa một cột mỗi lần, vì vậy chúng ta tạo ra nhiều câu lệnh.
     *
     * @param  \Core\Schema\Blueprint  $blueprint
     * @param  \Core\Support\Fluent  $command
     * @return array
     */
    protected function compileDropColumn(Blueprint $blueprint, Fluent $command): array
    {
        return array_map(function ($column) use ($blueprint) {
            return "ALTER TABLE {$this->quote($blueprint->getTableName())} DROP COLUMN " . $this->quote($column);
        }, $command->columns);
    }
}
