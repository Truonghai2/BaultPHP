<?php

namespace Core\Schema;

use Core\Support\Fluent;

/**
 * Represents a column definition in a database schema.
 * This class is a data carrier, using Fluent's magic properties.
 */
class ColumnDefinition extends Fluent
{
    /**
     * Specify that the column should be unique.
     *
     * @return $this
     */
    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }

    /**
     * Allow NULL values to be inserted into the column.
     *
     * @param  bool  $value
     * @return $this
     */
    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    /**
     * Specify a "default" value for the column.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function default($value): self
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Add a comment to the column.
     *
     * @param  string  $comment
     * @return $this
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Add a foreign key constraint.
     *
     * @param  string|null  $table
     * @param  string  $column
     * @return $this
     */
    public function constrained($table = null, $column = 'id'): self
    {
        if (is_null($table)) {
            $table = str_replace('_id', '', $this->name) . 's';
        }

        $this->foreign = ['table' => $table, 'column' => $column];

        return $this;
    }

    /**
     * Specify the action to take on delete.
     *
     * @param  string  $action
     * @return $this
     */
    public function onDelete(string $action): self
    {
        // FIX: Avoid indirect modification.
        // Get the array, modify it, and set it back.
        $foreign = $this->foreign;
        $foreign['onDelete'] = $action;
        $this->foreign = $foreign;

        return $this;
    }

    /**
     * Specify the action to take on update.
     *
     * @param  string  $action
     * @return $this
     */
    public function onUpdate(string $action): self
    {
        $foreign = $this->foreign;
        $foreign['onUpdate'] = $action;
        $this->foreign = $foreign;

        return $this;
    }

    /**
     * Specify that this is a column modification.
     *
     * @return $this
     */
    public function change(): self
    {
        $this->change = true;

        return $this;
    }

    /**
     * Specify that the column should be indexed.
     *
     * @param  string|null  $name
     * @return $this
     */
    public function index(?string $name = null): self
    {
        $this->index = [
            'name' => $name ?? $this->name . '_index',
            'columns' => [$this->name],
        ];

        return $this;
    }
}
