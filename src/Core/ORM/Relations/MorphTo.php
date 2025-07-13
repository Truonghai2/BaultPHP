<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class MorphTo extends Relation
{
    protected string $morphType;
    protected string $morphId;
    protected string $ownerKey;

    /**
     * A dictionary of models grouped by their type.
     * ['App\Models\Post' => [1, 2], 'App\Models\Video' => [3, 4]]
     * @var array
     */
    protected array $modelsByType = [];

    public function __construct(QueryBuilder $query, Model $parent, string $morphId, string $morphType, string $ownerKey)
    {
        $this->morphId = $morphId;
        $this->morphType = $morphType;
        $this->ownerKey = $ownerKey;

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        $type = $this->parent->getAttribute($this->morphType);
        $id = $this->parent->getAttribute($this->morphId);

        if (!$type || !$id) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $type;
        $query = $instance->newQuery();

        return $query->where($instance->getKeyName(), '=', $id)->first();
    }

    public function addEagerConstraints(array $models)
    {
        $this->modelsByType = [];
        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            if ($type) {
                $this->modelsByType[$type][] = $model->getAttribute($this->morphId);
            }
        }
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->getMorphClass()][$result->getKey()] = $result;
        }

        foreach ($models as $model) {
            $type = $model->getAttribute($this->morphType);
            $id = $model->getAttribute($this->morphId);

            if (isset($dictionary[$type][$id])) {
                $model->setRelation($relation, $dictionary[$type][$id]);
            }
        }

        return $models;
    }

    /**
     * Overrides the base get() method to perform multiple queries for each morph type.
     */
    public function get(): array
    {
        $results = [];

        foreach ($this->modelsByType as $type => $ids) {
            if (empty($ids)) continue;

            /** @var Model $instance */
            $instance = new $type;
            $query = $instance->newQuery();
            $typeResults = $query->whereIn($instance->getKeyName(), array_unique($ids))->get();
            $results = array_merge($results, $typeResults);
        }

        return $results;
    }

    public function getSelectCountSql(): string
    {
        throw new \LogicException('withCount() is not supported for MorphTo relationships.');
    }
}