<?php

namespace Core\ORM;

use Core\ORM\Scopes\SoftDeletingScope;

trait SoftDeletes
{
    /**
     * Boot the soft deleting trait for a model.
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope());
    }

    /**
     * Perform the actual delete query on this model instance.
     * Overrides the default delete to perform a soft delete.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        if (!$this->exists()) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = date('Y-m-d H:i:s');

        // The save method will only update dirty attributes, which now includes `deleted_at`.
        $result = $this->save();

        if ($result) {
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool
     */
    public function restore(): bool
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        // If the model is not trashed, there's nothing to do.
        if (!$this->trashed()) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;
        $result = $this->save();

        if ($result) {
            $this->fireModelEvent('restored');
        }

        return $result;
    }

    /**
     * Force a hard delete on a soft-deletable model instance.
     *
     * @return bool
     */
    public function forceDelete(): bool
    {
        if ($this->fireModelEvent('forceDeleting') === false) {
            return false;
        }

        $deleted = $this->newQuery()->where($this->getKeyName(), $this->getKey())->forceDelete();

        if ($deleted) {
            $this->fireModelEvent('forceDeleted');
        }

        return $deleted;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        return !is_null($this->getAttribute($this->getDeletedAtColumn()));
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->getTable() . '.' . $this->getDeletedAtColumn();
    }
}
