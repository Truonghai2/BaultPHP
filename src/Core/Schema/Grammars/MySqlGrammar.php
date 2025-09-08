<?php

namespace Core\Schema\Grammars;

use Core\ORM\QueryBuilder;
use Core\Schema\Blueprint;
use Core\Schema\ColumnDefinition;
use Core\Support\Fluent;

class MySqlGrammar implements Grammar
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

    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $rawCommand) {
            $command = new Fluent($rawCommand);
            $method = 'compile' . ucfirst($command->type);
            if (method_exists($this, $method)) {
                $sql = $this->{$method}($blueprint, $command);
                if (!is_null($sql)) {
                    $statements = array_merge($statements, (array)$sql);
                }
            }
        }

        foreach ($blueprint->getColumns() as $column) {
            if (!empty($column->change)) {
                $method = 'compileChange';
                if (method_exists($this, $method)) {
                    $sql = $this->{$method}($blueprint, $column);
                    if (!is_null($sql)) {
                        $statements = array_merge($statements, (array)$sql);
                    }
                }
            }
        }

        return $statements;
    }

    public function compileDrop(Blueprint $blueprint): array
    {
        return ["DROP TABLE IF EXISTS {$this->quote($blueprint->getTableName())}"];
    }

    public function compileColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql = $this->quote($column->name) . ' ' . $this->getType($column);

            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    protected function getType(ColumnDefinition $column): string
    {
        return $this->{'type' . ucfirst($column->type)}($column);
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

        if ($column->comment) {
            $sql .= " COMMENT '" . addslashes($column->comment) . "'";
        }

        return $sql;
    }

    protected function formatDefault($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value)) {
            return "'$value'";
        }
        if (is_null($value)) {
            return 'NULL';
        }
        return (string) $value;
    }

    public function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function typeId(ColumnDefinition $column): string
    {
        return $this->typeBigIncrements($column);
    }

    public function typeBigIncrements(ColumnDefinition $column): string
    {
        return 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    }

    public function typeIncrements(ColumnDefinition $column): string
    {
        return 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    }

    public function typeString(ColumnDefinition $column): string
    {
        return "VARCHAR({$column->length})";
    }

    public function typeText(ColumnDefinition $column): string
    {
        return 'TEXT';
    }

    public function typeInteger(ColumnDefinition $column): string
    {
        return 'INT';
    }

    public function typeBigInteger(ColumnDefinition $column): string
    {
        return 'BIGINT';
    }

    public function typeUnsignedBigInteger(ColumnDefinition $column): string
    {
        return 'BIGINT UNSIGNED';
    }

    public function typeUnsignedInteger(ColumnDefinition $column): string
    {
        return 'INT UNSIGNED';
    }

    // ... other type methods ...

    public function compileSelect(QueryBuilder $query): string
    {
        $sql = 'SELECT ' . implode(', ', $query->columns);
        $sql .= ' FROM ' . $this->quote($query->table);
        $sql .= $this->compileJoins($query);
        $sql .= $this->compileWheres($query);
        $sql .= $this->compileGroups($query);
        $sql .= $this->compileOrders($query);
        $sql .= $this->compileLimit($query);
        $sql .= $this->compileOffset($query);

        return $sql;
    }

    public function compileInsert(QueryBuilder $query, array $values): string
    {
        $table = $this->quote($query->table);
        $columns = implode(', ', array_map([$this, 'quote'], array_keys(reset($values))));

        $placeholders = implode(', ', array_map(function ($value) {
            return '(' . implode(', ', array_fill(0, count($value), '?')) . ')';
        }, $values));

        return "INSERT INTO {$table} ({$columns}) VALUES {$placeholders}";
    }

    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->quote($query->table);
        $columns = implode(', ', array_map(function ($key) {
            return $this->quote($key) . ' = ?';
        }, array_keys($values)));

        $wheres = $this->compileWheres($query);

        return "UPDATE {$table} SET {$columns}{$wheres}";
    }

    public function compileDelete(QueryBuilder $query): string
    {
        $table = $this->quote($query->table);
        $wheres = $this->compileWheres($query);

        return "DELETE FROM {$table}{$wheres}";
    }

    public function compileExists(QueryBuilder $query): string
    {
        return 'SELECT EXISTS(' . $this->compileSelect($query->limit(1)) . ')';
    }

    public function compileCount(QueryBuilder $query, string $columns): string
    {
        return 'SELECT COUNT(' . $columns . ') FROM ' . $this->quote($query->table) .
            $this->compileJoins($query) .
            $this->compileWheres($query);
    }

    public function compileWheres(QueryBuilder $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }

        $sql = [];
        foreach ($query->wheres as $where) {
            $sql[] = $this->quote($where['column']) . ' ' . $where['operator'] . ' ?';
        }

        return ' WHERE ' . implode(' AND ', $sql);
    }

    public function compileOrders(QueryBuilder $query): string
    {
        if (empty($query->orders)) {
            return '';
        }

        $sql = [];
        foreach ($query->orders as $order) {
            $sql[] = $this->quote($order['column']) . ' ' . $order['direction'];
        }

        return ' ORDER BY ' . implode(', ', $sql);
    }

    public function compileLimit(QueryBuilder $query): string
    {
        if (!is_null($query->limit)) {
            return ' LIMIT ' . (int) $query->limit;
        }
        return '';
    }

    public function compileOffset(QueryBuilder $query): string
    {
        if (!is_null($query->offset)) {
            return ' OFFSET ' . (int) $query->offset;
        }
        return '';
    }

    public function compileJoins(QueryBuilder $query): string
    {
        if (empty($query->joins)) {
            return '';
        }

        $sql = [];
        foreach ($query->joins as $join) {
            $sql[] = ' ' . $join['type'] . ' JOIN ' . $this->quote($join['table']) . ' ON ' .
                $this->quote($join['first']) . ' ' . $join['operator'] . ' ' . $this->quote($join['second']);
        }

        return implode('', $sql);
    }

    public function compileGroups(QueryBuilder $query): string
    {
        if (empty($query->groups)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', array_map([$this, 'quote'], $query->groups));
    }

    public function typeTinyInteger(ColumnDefinition $column): string
    {
        return 'TINYINT';
    }

    public function typeUnsignedTinyInteger(ColumnDefinition $column): string
    {
        return 'TINYINT UNSIGNED';
    }

    public function typeTimestamp(ColumnDefinition $column): string
    {
        return 'TIMESTAMP';
    }

    public function typeBoolean(ColumnDefinition $column): string
    {
        return 'TINYINT(1)';
    }

    public function typeDecimal(ColumnDefinition $column): string
    {
        return "DECIMAL({$column->precision}, {$column->scale})";
    }

    public function typeDate(ColumnDefinition $column): string
    {
        return 'DATE';
    }

    public function typeTime(ColumnDefinition $column): string
    {
        return 'TIME';
    }

    public function typeDatetime(ColumnDefinition $column): string
    {
        return 'DATETIME';
    }

    public function typeJson(ColumnDefinition $column): string
    {
        return 'JSON';
    }

    public function typeEnum(ColumnDefinition $column): string
    {
        return "ENUM('" . implode("', '", $column->allowed) . "')";
    }

    public function typeSet(ColumnDefinition $column): string
    {
        return "SET('" . implode("', '", $column->allowed) . "')";
    }

    public function typeUuid(ColumnDefinition $column): string
    {
        return 'CHAR(36)';
    }

    public function typeLongText(ColumnDefinition $column): string
    {
        return 'LONGTEXT';
    }

    protected function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileKey($blueprint, $command, 'UNIQUE');
    }

    protected function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileKey($blueprint, $command, 'INDEX');
    }

    protected function compileKey(Blueprint $blueprint, Fluent $command, string $type): string
    {
        $columns = implode(', ', array_map([$this, 'quote'], $command->columns));
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);

        return "ALTER TABLE {$table} ADD {$type} {$index}({$columns})";
    }

    protected function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    protected function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        $table = $this->quote($blueprint->getTableName());
        $index = $this->quote($command->name);

        return "ALTER TABLE {$table} DROP INDEX {$index}";
    }

    protected function compileDropColumn(Blueprint $blueprint, Fluent $command): string
    {
        $columns = implode(', ', array_map(fn ($c) => 'DROP COLUMN ' . $this->quote($c), $command->columns));
        return "ALTER TABLE {$this->quote($blueprint->getTableName())} {$columns}";
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

    protected function compileForeignKey(Blueprint $blueprint, ColumnDefinition $column): string
    {
        $onDelete = isset($column->foreign['onDelete']) ? ' ON DELETE ' . strtoupper($column->foreign['onDelete']) : '';
        $onUpdate = isset($column->foreign['onUpdate']) ? ' ON UPDATE ' . strtoupper($column->foreign['onUpdate']) : '';

        $constraintName = $this->quote($blueprint->getTableName() . '_' . $column->name . '_foreign');

        return "CONSTRAINT {$constraintName} FOREIGN KEY ({$this->quote($column->name)}) "
            . "REFERENCES {$this->quote($column->foreign['table'])} ({$this->quote($column->foreign['column'])}){$onDelete}{$onUpdate}";
    }

    /**
     * Biên dịch một lệnh thay đổi cột thành câu truy vấn.
     *
     * @param  \Core\Schema\Blueprint  $blueprint
     * @param  \Core\Schema\ColumnDefinition  $column
     * @return string
     */
    protected function compileChange(Blueprint $blueprint, ColumnDefinition $column): string
    {
        $sql = $this->quote($column->name) . ' ' . $this->getType($column);

        return "ALTER TABLE {$this->quote($blueprint->getTableName())} MODIFY COLUMN " . $this->addModifiers($sql, $blueprint, $column);
    }

    /**
     * Biên dịch một lệnh đổi tên cột thành câu truy vấn.
     *
     * @param  \Core\Schema\Blueprint  $blueprint
     * @param  \Core\Support\Fluent  $command
     * @return string
     */
    protected function compileRenameColumn(Blueprint $blueprint, Fluent $command): string
    {
        return "ALTER TABLE {$this->quote($blueprint->getTableName())} RENAME COLUMN {$this->quote($command->from)} TO {$this->quote($command->to)}";
    }
}
