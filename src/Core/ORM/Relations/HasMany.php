<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class HasMany extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        $localKeyValue = $this->parent->getAttribute($this->localKey);

        return $this->query->where($this->foreignKey, '=', $localKeyValue)->get();
    }

    public function addEagerConstraints(array $models)
    {
        $keys = array_map(fn($model) => $model->getAttribute($this->localKey), $models);
        $this->query->where($this->foreignKey, 'IN', array_unique($keys));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, []); // Set empty array if no related models found
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->foreignKey)][] = $result;
        }
        return $dictionary;
    }

    public function getSelectCountSql(): string
    {
        $relatedTable = $this->query->getModel()->getTable();
        $parentTable = $this->parent->getTable();

        // e.g., SELECT count(*) FROM comments WHERE comments.post_id = posts.id
        $sql = "SELECT count(*) FROM `{$relatedTable}` WHERE `{$relatedTable}`.`{$this->foreignKey}` = `{$parentTable}`.`{$this->localKey}`";

        return $sql;
    }
}