<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class BelongsTo extends Relation
{
    protected string $foreignKey; // Key on the parent model (e.g., user_id on Post)
    protected string $ownerKey;   // Key on the related model (e.g., id on User)

    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $ownerKey)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

        return $this->query->where($this->ownerKey, '=', $foreignKeyValue)->first();
    }

    public function addEagerConstraints(array $models)
    {
        $keys = array_map(fn ($model) => $model->getAttribute($this->foreignKey), $models);
        $this->query->where($this->ownerKey, 'IN', array_unique($keys));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->ownerKey)] = $result;
        }
        return $dictionary;
    }

    public function getSelectCountSql(): string
    {
        $relatedTable = $this->query->getModel()->getTable();
        $parentTable = $this->parent->getTable();

        $sql = "SELECT count(*) FROM `{$relatedTable}` WHERE `{$relatedTable}`.`{$this->ownerKey}` = `{$parentTable}`.`{$this->foreignKey}`";

        return $sql;
    }
}
