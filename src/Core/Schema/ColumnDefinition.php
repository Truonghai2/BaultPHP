<?php

namespace Core\Schema;

class ColumnDefinition
{
    protected string $sql;
    protected bool $isUnique = false;
    protected $defaultValue = null;
    protected bool $hasDefault = false;
    protected bool $isNullable = false;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    /**
     * Specify a "default" value for the column.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function default($value): self
    {
        $this->hasDefault = true;
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * Allow NULL values to be inserted into the column.
     * @return $this
     */
    public function nullable(): self
    {
        $this->isNullable = true;
        return $this;
    }

    public function unique(): self
    {
        $this->isUnique = true;
        return $this;
    }

    public function getSql(string $column): array
    {
        $baseSql = $this->sql;

        if ($this->isNullable) {
            $baseSql .= ' NULL';
        } else {
            $baseSql .= ' NOT NULL';
        }

        if ($this->hasDefault) {
            if (is_string($this->defaultValue)) {
                $baseSql .= " DEFAULT '" . addslashes($this->defaultValue) . "'";
            } elseif (is_bool($this->defaultValue)) {
                $baseSql .= " DEFAULT " . ($this->defaultValue ? '1' : '0');
            } elseif (is_null($this->defaultValue)) {
                $baseSql .= " DEFAULT NULL";
            } else {
                $baseSql .= " DEFAULT " . $this->defaultValue;
            }
        }

        $sqls = [$baseSql];

        if ($this->isUnique) {
            $sqls[] = "UNIQUE (`$column`)";
        }

        return $sqls;
    }

    public function setOption(string $key, mixed $value): void
    {
        switch ($key) {
            case 'default':
                $this->default($value);
                break;
            case 'nullable':
                if ($value) {
                    $this->nullable();
                }
                break;
            case 'unique':
                if ($value) {
                    $this->unique();
                }
                break;
            default:
                throw new \InvalidArgumentException("Unknown option '$key' for ColumnDefinition.");
        }
    }

    public function useCurrent(): self
    {
        $this->default('CURRENT_TIMESTAMP');
        return $this;
    }
}
