<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;

/**
 * Represents the pivot table record in a many-to-many relationship.
 * This is a simple, non-persisted model used to hold pivot attributes.
 */
class Pivot extends Model
{
    /**
     * The table associated with the pivot model.
     * This is intentionally empty as Pivot models are not saved directly.
     *
     * @var string
     */
    protected static string $table = '';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected static string $primaryKey = '';

    /**
     * Indicates if the model exists in the database.
     * Pivot models are transient and never "exist" on their own.
     */
    public function exists(): bool
    {
        return false;
    }
}
