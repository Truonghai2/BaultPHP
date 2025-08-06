<?php

namespace Core\Schema;

/**
 * Represents a foreign key constraint definition.
 * This class is used to build foreign key constraints in a fluent way.
 */
class ForeignKeyDefinition
{
    protected array $columns;
    protected string $references;
    protected string $onTable;
    protected ?string $onDelete = null;
    protected ?string $onUpdate = null;
    protected ?string $name = null;

    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->onTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSql(string $tableName): string
    {
        $columnsSql = implode(', ', array_map(fn ($col) => "`$col`", $this->columns));
        $constraintName = $this->name ?? "{$tableName}_" . implode('_', $this->columns) . '_foreign';

        $sql = "CONSTRAINT `{$constraintName}` FOREIGN KEY ({$columnsSql}) REFERENCES `{$this->onTable}` (`{$this->references}`)";

        if ($this->onDelete) {
            $sql .= " ON DELETE {$this->onDelete}";
        }
        if ($this->onUpdate) {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }
}
