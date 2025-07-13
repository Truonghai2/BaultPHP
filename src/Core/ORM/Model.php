<?php

namespace Core\ORM;
use Core\ORM\Events\Observable;
use Core\ORM\Scopes\Scope;
use Core\ORM\SoftDeletes;
use Core\ORM\Exceptions\ModelNotFoundException;

use PDO;
use Core\ORM\QueryBuilder;
use Core\ORM\Relations\Relation;
use Core\ORM\Relations\HasMany;
use Core\ORM\Relations\BelongsTo;
use Core\ORM\Relations\BelongsToMany;
use Core\ORM\Relations\MorphMany;
use Core\ORM\Relations\MorphTo;

abstract class Model
{
    use Observable;
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $original = [];

    /**
     * The model's fillable attributes.
     */
    protected array $fillable = [];

    /**
     * The attributes that are mass sortable.
     */
    protected array $sortable = [];

    /**
     * The attributes that are mass selectable.
     */
    protected array $selectable = [];

    /**
     * The attributes that can be used in a group by clause.
     */
    protected array $groupable = [];

    /**
     * The attributes that can be used in a where clause.
     */
    protected array $filterable = [];

    /**
     * The relationships that should be touched on save.
     */
    protected array $touches = [];

    /**
     * The array of global scopes on the model.
     * @var array
     */
    protected static array $globalScopes = [];

    /**
     * The array of booted models.
     * @var array
     */
    protected static array $booted = [];

    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Set a given relationship on the model.
     */
    public function setRelation(string $relation, $value): void
    {
        $this->relations[$relation] = $value;
    }

    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->{$key}();
            if ($relation instanceof Relation) {
                return $this->relations[$key] = $relation->getResults();
            }
        }

        return null;
    }

    protected function isFillable(string $key): bool
    {
        if (empty($this->fillable)) {
            return true;
        }
        return in_array($key, $this->fillable);
    }

    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $query = $this->newQuery();

        if ($this->exists()) {
            $dirty = $this->getDirty();

            if (empty($dirty)) {
                $this->fireModelEvent('saved');
                return true; // Nothing to update
            }

            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            if ($query->where($this->getKeyName(), $this->getKey())->update($dirty)) {
                $this->syncOriginal();
                $this->fireModelEvent('updated');
                $this->fireModelEvent('saved');
                $this->touchRelations();
                return true;
            }

            return false;
        }

        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $id = $query->insertGetId($this->attributes);
        if ($id) {
            $this->setAttribute($this->getKeyName(), $id);
            $this->syncOriginal();
            $this->fireModelEvent('created');
            $this->fireModelEvent('saved');
            $this->touchRelations();
            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $deleted = $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();

        if ($deleted) {
            $this->fireModelEvent('deleted');
            $this->touchRelations();
        }

        return $deleted;
    }

    /**
     * Determine if the model uses soft deletes.
     */
    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class));
    }

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $column = $this->getUpdatedAtColumn();
        $this->setAttribute($column, date('Y-m-d H:i:s'));

        return $this->newQuery()->where($this->getKeyName(), $this->getKey())->update([
            $column => $this->getAttribute($column)
        ]);
    }

    /**
     * Touch the owning relations of the model.
     */
    protected function touchRelations(): void
    {
        foreach ($this->touches as $relation) {
            $parent = $this->{$relation}; // This will lazy-load the relation
            $parent?->touch();
        }
    }

    public function exists(): bool
    {
        return isset($this->original[$this->getKeyName()]);
    }

    /**
     * Get the attributes that have been changed since the last sync.
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function getKeyName(): string
    {
        return static::$primaryKey;
    }

    public function getTable(): string
    {
        return static::$table;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn(): string
    {
        return defined('static::CREATED_AT') ? static::CREATED_AT : 'created_at';
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn(): string
    {
        return defined('static::UPDATED_AT') ? static::UPDATED_AT : 'updated_at';
    }

    /**
     * Get the sortable attributes for the model.
     *
     * @return array
     */
    public function getSortableColumns(): array
    {
        return $this->sortable;
    }

    /**
     * Get the selectable attributes for the model.
     *
     * @return array
     */
    public function getSelectableColumns(): array
    {
        return $this->selectable;
    }

    /**
     * Get the groupable attributes for the model.
     *
     * @return array
     */
    public function getGroupableColumns(): array
    {
        return $this->groupable;
    }

    /**
     * Get the filterable attributes for the model.
     *
     * @return array
     */
    public function getFilterableColumns(): array
    {
        return $this->filterable;
    }

    public static function create(array $attributes = []): ?static
    {
        $model = new static($attributes);
        if ($model->save()) {
            return $model;
        }
        return null;
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return static|null
     */
    public static function firstOrCreate(array $attributes, array $values = []): ?static
    {
        $query = static::query();
        foreach ($attributes as $key => $value) {
            $query->where($key, '=', $value);
        }

        if (!is_null($instance = $query->first())) {
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return static|null
     */
    public static function updateOrCreate(array $attributes, array $values = []): ?static
    {
        $query = static::query();
        foreach ($attributes as $key => $value) {
            $query->where($key, '=', $value);
        }

        if (!is_null($instance = $query->first())) {
            $instance->fill($values);
            $instance->save();
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function find(int $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  int  $id
     * @return static
     *
     * @throws \Core\ORM\Exceptions\ModelNotFoundException
     */
    public static function findOrFail(int $id): static
    {
        return static::query()->findOrFail($id);
    }

    /**
     * Find a model by its primary key or call a callback.
     *
     * @param  int  $id
     * @param  \Closure  $callback
     * @return mixed
     */
    public static function findOr(int $id, \Closure $callback): mixed
    {
        return static::query()->findOr($id, $callback);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function where(string $column, string $operator, $value = null): QueryBuilder
    {
        return static::query()->where(...func_get_args());
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic($method, $parameters)
    {
        // Forward the call to a new query builder instance
        return static::query()->$method(...$parameters);
    }

    public static function query(): QueryBuilder
    {
        return (new static)->newQuery();
    }

    public function newQuery(): QueryBuilder
    {
        return $this->newQueryWithoutScopes()->applyGlobalScopes();
    }

    public function newQueryWithoutScopes(): QueryBuilder
    {
        $this->bootIfNotBooted();
        return (new QueryBuilder(static::class))->table(static::$table);
    }

    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->bootTraits();
            static::boot();
        }
    }

    protected static function boot(): void
    {
        // Can be overridden in child models
    }

    protected function bootTraits(): void
    {
        $class = static::class;
        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);
            if (method_exists($class, $method)) {
                forward_static_call([$class, $method]);
            }
        }
    }

    public static function addGlobalScope(Scope $scope): void
    {
        static::$globalScopes[static::class][get_class($scope)] = $scope;
    }

    public function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower(basename(str_replace('\\', '/', get_class($this)))) . '_' . $this->getKeyName();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower(basename(str_replace('\\', '/', $related))) . '_' . $instance->getKeyName();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey);
    }

    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null
    ): BelongsToMany {
        $instance = new $related;

        // Guess pivot table name
        $table = $table ?: $this->getPivotTableName($related);

        $foreignPivotKey = $foreignPivotKey ?: strtolower(basename(str_replace('\\', '/', get_class($this)))) . '_' . $this->getKeyName();
        $relatedPivotKey = $relatedPivotKey ?: strtolower(basename(str_replace('\\', '/', $related))) . '_' . $instance->getKeyName();

        return new BelongsToMany($instance->newQuery(), $this, $table, $foreignPivotKey, $relatedPivotKey, $this->getKeyName(), $instance->getKeyName());
    }

    private function getPivotTableName(string $related): string
    {
        $models = [
            strtolower(basename(str_replace('\\', '/', get_class($this)))),
            strtolower(basename(str_replace('\\', '/', $related)))
        ];
        sort($models);
        return implode('_', $models);
    }

    /**
     * Define a one-to-many polymorphic relationship.
     */
    protected function morphMany(string $related, string $name): MorphMany
    {
        $instance = new $related;

        // e.g., from 'commentable', we get 'commentable_type' and 'commentable_id'
        $morphType = $name . '_type';
        $foreignKey = $name . '_id';

        $localKey = $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $morphType, $foreignKey, $localKey);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     */
    protected function morphTo(string $name = null, string $type = null, string $id = null): MorphTo
    {
        $name = $name ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        $type = $type ?: $name . '_type';
        $id = $id ?: $name . '_id';

        $ownerKey = 'id'; // This is almost always the primary key of the related models.
        return new MorphTo($this->newQueryWithoutScopes(), $this, $id, $type, $ownerKey);
    }

    public function getMorphClass(): string
    {
        return static::class;
    }

    public function newFromBuilder(array $attributes = []): static
    {
        $model = new static;
        $model->attributes = $attributes;
        $model->original = $attributes;
        return $model;
    }

    /**
     * Forget an attribute from the model. Used for temporary pivot attributes.
     */
    public function forgetAttribute(string $key): void
    {
        unset($this->attributes[$key]);
    }
}
