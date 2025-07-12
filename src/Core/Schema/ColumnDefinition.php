<?php

namespace Core\Schema;

class ColumnDefinition
{
    protected string $sql;
    protected bool $isUnique = false;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    public function unique(): self
    {
        $this->isUnique = true;
        return $this;
    }

    public function getSql(string $column): array
    {
        $sqls = [$this->sql];

        if ($this->isUnique) {
            $sqls[] = "UNIQUE (`$column`)";
        }

        return $sqls;
    }
}
