<?php

namespace Core\Schema;

class ColumnDefinition
{
    protected string $sql;
    public bool $isUnique = false;
    protected $defaultValue = null;
    protected bool $hasDefault = false;
    protected bool $isNullable = false;
    public bool $isIndex = false;
    protected ?string $comment = null;

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

    /**
     * Add an index to the column.
     *
     * @return $this
     */
    public function index(): self
    {
        $this->isIndex = true;
        return $this;
    }

    /**
     * Add a comment to the column.
     *
     * @param string $comment
     * @return $this
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getSql(): string
    {
        $baseSql = $this->sql;

        if ($this->isNullable) {
            $baseSql .= ' NULL';
        } else {
            $baseSql .= ' NOT NULL';
        }

        if ($this->hasDefault) {
            if (is_null($this->defaultValue)) {
                $baseSql .= ' DEFAULT NULL';
            } elseif (is_string($this->defaultValue)) {
                // Handle special keywords like CURRENT_TIMESTAMP without quotes
                if (in_array(strtoupper($this->defaultValue), ['CURRENT_TIMESTAMP'])) {
                    $baseSql .= ' DEFAULT ' . $this->defaultValue;
                } else {
                    $baseSql .= " DEFAULT '" . addslashes($this->defaultValue) . "'";
                }
            } elseif (is_bool($this->defaultValue)) {
                $baseSql .= ' DEFAULT ' . ($this->defaultValue ? '1' : '0');
            } else {
                $baseSql .= ' DEFAULT ' . $this->defaultValue;
            }
        }

        if ($this->comment) {
            $baseSql .= " COMMENT '" . addslashes($this->comment) . "'";
        }

        return $baseSql;
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
            case 'index':
                if ($value) {
                    $this->index();
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
