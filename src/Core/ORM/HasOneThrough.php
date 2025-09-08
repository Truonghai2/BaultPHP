<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class HasOneThrough extends Relation
{
    /**
     * The "through" parent model instance.
     * @var \Core\ORM\Model
     */
    protected Model $through;

    /**
     * The foreign key on the "through" model.
     * @var string
     */
    protected string $firstKey;

    /**
     * The foreign key on the final model.
     * @var string
     */
    protected string $secondKey;

    /**
     * The local key on the parent model.
     * @var string
     */
    protected string $localKey;

    /**
     * The local key on the "through" model.
     * @var string
     */
    protected string $secondLocalKey;

    public function __construct(QueryBuilder $query, Model $parent, Model $through, string $firstKey, string $secondKey, string $localKey, string $secondLocalKey)
    {
        $this->through = $through;
        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
        $this->localKey = $localKey;
        $this->secondLocalKey = $secondLocalKey;

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        return $this->prepareQuery()->first();
    }

    public function addEagerConstraints(array $models)
    {
        $keys = array_map(fn ($model) => $model->getAttribute($this->localKey), $models);
        $this->query->where($this->through->getTable() . '.' . $this->firstKey, 'IN', array_unique($keys));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
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
            $throughKey = $result->getAttribute('bault_through_key');
            $dictionary[$throughKey] = $result;
        }
        return $dictionary;
    }

    public function getSelectCountSql(): string
    {
        $farTable = $this->query->getModel()->getTable();
        $throughTable = $this->through->getTable();
        $parentTable = $this->parent->getTable();

        $joinClause = "INNER JOIN `{$throughTable}` ON `{$throughTable}`.`{$this->secondLocalKey}` = `{$farTable}`.`{$this->secondKey}`";
        $whereClause = "WHERE `{$throughTable}`.`{$this->firstKey}` = `{$parentTable}`.`{$this->localKey}`";

        return "SELECT count(*) FROM `{$farTable}` {$joinClause} {$whereClause}";
    }

    /**
     * Prepare the query for execution.
     *
     * @return \Core\ORM\QueryBuilder
     */
    protected function prepareQuery(): QueryBuilder
    {
        $farTable = $this->query->getModel()->getTable();
        $throughTable = $this->through->getTable();

        $this->query->select(
            "{$farTable}.*",
            "{$throughTable}.{$this->firstKey} as bault_through_key",
        );

        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$farTable}.{$this->secondKey}",
        );

        $this->query->where(
            "{$throughTable}.{$this->firstKey}",
            '=',
            $this->parent->getAttribute($this->localKey),
        );

        return $this->query;
    }
}
