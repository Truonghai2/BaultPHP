<?php

namespace Core\ORM;

use Core\ORM\Events\Observable;
use Core\ORM\Relations\BelongsTo;
use Core\ORM\Relations\BelongsToMany;
use Core\ORM\Relations\HasMany;
use Core\ORM\Relations\MorphedByMany;
use Core\ORM\Relations\MorphMany;
use Core\ORM\Relations\MorphTo;
use Core\ORM\Relations\MorphToMany;
use Core\ORM\Relations\Relation;
use Core\ORM\Scopes\Scope;
use Core\Support\Collection;

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
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = ['*'];

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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public bool $timestamps = true;

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

    public function isRelationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get the original attributes of the model.
     *
     * @return array
     */
    public function getOriginal(): array
    {
        return $this->original;
    }

    /**
     * Get all the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    protected function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        if ($this->guarded === ['*']) {
            return false;
        }

        return !in_array($key, $this->guarded);
    }

    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $query = $this->newQuery();

        // Update timestamps before saving
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        if ($this->exists()) {
            $dirty = $this->getDirty();

            if (empty($dirty)) {
                $this->fireModelEvent('saved');
                return true;
            }

            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            if ($query->where($this->getKeyName(), $this->getKey())->update($dirty) > 0) {
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
            $column => $this->getAttribute($column),
        ]) > 0;
    }

    /**
     * Touch the owning relations of the model.
     */
    protected function touchRelations(): void
    {
        foreach ($this->touches as $relation) {
            $parent = $this->{$relation};
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

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     *
     * @param  string|array|null  $attributes
     * @return bool
     */
    public function isDirty($attributes = null): bool
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        $attributes = is_array($attributes) ? $attributes : [$attributes];

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
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
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
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
     * Get a fresh timestamp for the model.
     *
     * @return string
     */
    public function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Set the value of the "created at" attribute.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCreatedAt(string $value): static
    {
        $this->setAttribute($this->getCreatedAtColumn(), $value);
        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     *
     * @param  string  $value
     * @return $this
     */
    public function setUpdatedAt(string $value): static
    {
        $this->setAttribute($this->getUpdatedAtColumn(), $value);
        return $this;
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestamp();

        if (!$this->exists() && !$this->isDirty($this->getCreatedAtColumn())) {
            $this->setCreatedAt($time);
        }

        if (!$this->isDirty($this->getUpdatedAtColumn())) {
            $this->setUpdatedAt($time);
        }
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
        $model = new static();
        $model->fill($attributes);
        $model->save();
        return $model->exists() ? $model : null;
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

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public static function where(string $column, $operator = null, $value = null): QueryBuilder
    {
        // Support both 2 and 3 argument syntax like Laravel
        // where('name', 'john') => where('name', '=', 'john')
        // where('id', '>', 5) => where('id', '>', 5)
        if (func_num_args() === 2) {
            return static::query()->where($column, '=', $operator);
        }
        return static::query()->where($column, $operator, $value);
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic($method, $parameters)
    {
        return static::query()->$method(...$parameters);
    }

    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
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
        $instance = new $related();
        $foreignKey = $foreignKey ?: strtolower(basename(str_replace('\\', '/', get_class($this)))) . '_' . $this->getKeyName();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?: strtolower(basename(str_replace('\\', '/', $related))) . '_' . $instance->getKeyName();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey);
    }

    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
    ): BelongsToMany {
        $instance = new $related();

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
            strtolower(basename(str_replace('\\', '/', $related))),
        ];
        sort($models);
        return implode('_', $models);
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param  string  $related The final related model class.
     * @param  string  $through The intermediate model class.
     * @param  string|null  $firstKey Foreign key on the "through" model.
     * @param  string|null  $secondKey Foreign key on the "related" model.
     * @param  string|null  $localKey Local key on the parent model.
     * @param  string|null  $secondLocalKey Local key on the "through" model.
     * @return \Core\ORM\Relations\HasManyThrough
     */
    protected function hasManyThrough(string $related, string $through, ?string $firstKey = null, ?string $secondKey = null, ?string $localKey = null, ?string $secondLocalKey = null): Relations\HasManyThrough
    {
        $throughInstance = new $through();
        $relatedInstance = new $related();

        $localKey = $localKey ?: $this->getKeyName();
        $firstKey = $firstKey ?: strtolower(basename(str_replace('\\', '/', get_class($this)))) . '_' . $this->getKeyName();
        $secondLocalKey = $secondLocalKey ?: $throughInstance->getKeyName();
        $secondKey = $secondKey ?: strtolower(basename(str_replace('\\', '/', $through))) . '_' . $throughInstance->getKeyName();

        return new Relations\HasManyThrough($relatedInstance->newQuery(), $this, $throughInstance, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Define a has-one-through relationship.
     *
     * @param  string  $related The final related model class.
     * @param  string  $through The intermediate model class.
     * @param  string|null  $firstKey Foreign key on the "through" model.
     * @param  string|null  $secondKey Foreign key on the "related" model.
     * @param  string|null  $localKey Local key on the parent model.
     * @param  string|null  $secondLocalKey Local key on the "through" model.
     * @return \Core\ORM\Relations\HasOneThrough
     */
    protected function hasOneThrough(string $related, string $through, ?string $firstKey = null, ?string $secondKey = null, ?string $localKey = null, ?string $secondLocalKey = null): Relations\HasOneThrough
    {
        $throughInstance = new $through();
        $relatedInstance = new $related();

        $localKey = $localKey ?: $this->getKeyName();
        $firstKey = $firstKey ?: strtolower(basename(str_replace('\\', '/', get_class($this)))) . '_' . $this->getKeyName();
        $secondLocalKey = $secondLocalKey ?: $throughInstance->getKeyName();
        $secondKey = $secondKey ?: strtolower(basename(str_replace('\\', '/', $through))) . '_' . $throughInstance->getKeyName();

        return new Relations\HasOneThrough($relatedInstance->newQuery(), $this, $throughInstance, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Define a one-to-many polymorphic relationship.
     */
    protected function morphMany(string $related, string $name): MorphMany
    {
        $instance = new $related();

        $morphType = $name . '_type';
        $foreignKey = $name . '_id';

        $localKey = $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $morphType, $foreignKey, $localKey);
    }

    /**
     * Define a many-to-many polymorphic relationship.
     *
     * @param  string  $related The related model class.
     * @param  string  $name The relationship name (used for morph type and id).
     * @param  string|null  $table The pivot table name.
     * @param  string|null  $foreignPivotKey The foreign key of the parent model on the pivot table.
     * @param  string|null  $relatedPivotKey The foreign key of the related model on the pivot table.
     * @return \Core\ORM\Relations\MorphToMany
     */
    protected function morphToMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): MorphToMany
    {
        $instance = new $related();
        $relatedPivotKey = $relatedPivotKey ?: strtolower(basename(str_replace('\\', '/', $related))) . '_id';

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';
        $morphType = $name . '_type';

        $table = $table ?: $name . 's'; // e.g., 'taggable' becomes 'taggables'

        return new MorphToMany($instance->newQuery(), $this, $table, $foreignPivotKey, $relatedPivotKey, $this->getKeyName(), $instance->getKeyName(), $morphType);
    }

    /**
     * Define a "morphed by many" relationship.
     * This is the inverse of a morphToMany relationship.
     *
     * @param  string  $related The related model class.
     * @param  string  $name The relationship name.
     * @param  string|null  $table The pivot table name.
     * @param  string|null  $foreignPivotKey The foreign key of the current model on the pivot table.
     * @param  string|null  $relatedPivotKey The foreign key of the related model on the pivot table.
     * @return \Core\ORM\Relations\MorphedByMany
     */
    protected function morphedByMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): MorphedByMany
    {
        $instance = new $related();
        $foreignPivotKey = $foreignPivotKey ?: strtolower(basename(str_replace('\\', '/', get_class($this)))) . '_id';
        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';
        $morphType = $name . '_type';
        $table = $table ?: $name . 's';

        return new MorphedByMany($instance->newQuery(), $this, $table, $foreignPivotKey, $relatedPivotKey, $this->getKeyName(), $instance->getKeyName(), $morphType);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     */
    protected function morphTo(string $name = null, string $type = null, string $id = null): MorphTo
    {
        $relationName = $name ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        $type = $type ?: $relationName . '_type';
        $id = $id ?: $relationName . '_id';

        $ownerKey = 'id';
        return new MorphTo($this->newQueryWithoutScopes(), $this, $id, $type, $ownerKey, $relationName);
    }

    public function getMorphClass(): string
    {
        return static::class;
    }

    /**
     * Load a relationship on the current model instance.
     *
     * @param  string|array  $relations
     * @return $this
     */
    public function load($relations): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $query = $this->newQuery();
        $query->loadRelations([$this], $relations);

        return $this;
    }

    /**
     * Load a relationship on the model if it is not already loaded.
     *
     * @param  string|array  $relations
     * @return $this
     */
    public function loadMissing($relations): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $relationsToLoad = [];

        foreach ($relations as $name => $constraints) {
            $relationName = is_numeric($name) ? $constraints : $name;
            $topLevelRelation = explode('.', $relationName)[0];

            if (!$this->isRelationLoaded($topLevelRelation)) {
                $relationsToLoad[$name] = $constraints;
            }
        }

        if (!empty($relationsToLoad)) {
            $this->load($relationsToLoad);
        }

        return $this;
    }

    /**
     * Load a polymorphic relationship's nested relations conditionally.
     *
     * @param  string  $relation The name of the MorphTo relationship.
     * @param  array   $relations An array mapping morphable types to the relations to load.
     * @return $this
     */
    public function loadMorph(string $relation, array $relations): static
    {
        if (!$this->isRelationLoaded($relation)) {
            $this->load($relation);
        }

        $relatedModel = $this->getRelationValue($relation);

        if ($relatedModel) {
            $morphType = get_class($relatedModel);

            if (isset($relations[$morphType])) {
                $relatedModel->load($relations[$morphType]);
            }
        }

        return $this;
    }

    /**
     * Load a relationship count on the current model instance.
     *
     * @param  string|array  $relations
     * @return $this
     */
    public function loadCount($relations): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $query = $this->newQuery();
        $query->loadCount(new Collection([$this]), $relations);

        return $this;
    }

    public function getRelationValue(string $key)
    {
        return $this->relations[$key] ?? null;
    }

    public function newFromBuilder(array $attributes = []): static
    {
        $model = new static();
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

    /**
     * Replicate the model into a new, non-existent instance.
     *
     * @return static
     */
    public function replicate(): static
    {
        $clone = new static();

        $clone->attributes = $this->attributes;

        unset($clone->attributes[$this->getKeyName()]);

        return $clone;
    }
}
