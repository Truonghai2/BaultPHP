<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class MorphTo extends Relation
{
    protected string $morphType;
    protected string $morphId; // This is the foreign key on the parent model
    protected string $ownerKey;
    protected string $relationName;

    /**
     * A dictionary of models grouped by their type.
     * ['App\Models\Post' => [1, 2], 'App\Models\Video' => [3, 4]]
     * @var array
     * @deprecated This logic is now handled in QueryBuilder::eagerLoadMorphTo
     */
    protected array $modelsByType = [];

    public function __construct(QueryBuilder $query, Model $parent, string $morphId, string $morphType, string $ownerKey, string $relationName)
    {
        $this->morphId = $morphId;
        $this->morphType = $morphType;
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;

        parent::__construct($query, $parent);
    }

    /**
     * Get the results of the relationship for the parent model.
     * This is for lazy loading.
     */
    public function getResults()
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->morphId);

        if (!$type || !$id) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $type();
        $query = $instance->newQuery();

        // The owner key is the primary key of the related model.
        return $query->where($instance->getKeyName(), '=', $id)->first();
    }

    /**
     * Associate the model instance to the parent.
     *
     * @param  \Core\ORM\Model  $model
     * @return \Core\ORM\Model The parent model.
     */
    public function associate(Model $model): Model
    {
        $this->parent->setAttribute($this->morphId, $model->getKey());
        $this->parent->setAttribute($this->morphType, $model->getMorphClass());

        // Also set the relation on the parent model so it's available immediately
        // without another query.
        $this->parent->setRelation($this->relationName, $model);

        return $this->parent;
    }

    /**
     * Dissociate the model from the parent.
     *
     * @return \Core\ORM\Model The parent model.
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->morphId, null);
        $this->parent->setAttribute($this->morphType, null);

        $this->parent->setRelation($this->relationName, null);

        return $this->parent;
    }

    /**
     * This method is not used for MorphTo relationships as the eager loading
     * logic is handled specially in the QueryBuilder.
     */
    public function addEagerConstraints(array $models)
    {
        // This is intentionally left empty.
        // The logic is in QueryBuilder::eagerLoadMorphTo
    }

    /**
     * This method is not used for MorphTo relationships as the eager loading
     * logic is handled specially in the QueryBuilder.
     */
    public function match(array $models, array $results, string $relation): array
    {
        // This is intentionally left empty.
        // The logic is in QueryBuilder::eagerLoadMorphTo
        return $models;
    }

    public function getSelectCountSql(): string
    {
        throw new \LogicException('withCount() is not supported for MorphTo relationships.');
    }

    /**
     * Get the name of the "type" column.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the name of the foreign key column.
     */
    public function getForeignKey(): string
    {
        return $this->morphId;
    }

    /**
     * Get the name of the owner key column.
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }
}
