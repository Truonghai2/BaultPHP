<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;
use Core\Support\Collection;

class BelongsToMany extends Relation
{
    protected string $relatedTable;
    protected string $pivotTable;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
    ) {
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relatedTable = $query->getModel()->getTable();

        parent::__construct($query, $parent);
    }

    public function getResults()
    {
        $this->query->select("{$this->relatedTable}.*");
        $this->query->join(
            $this->pivotTable,
            "{$this->relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}",
        );
        $this->query->where(
            "{$this->pivotTable}.{$this->foreignPivotKey}",
            '=',
            $this->parent->getAttribute($this->parentKey),
        );

        return $this->query->get();
    }

    public function addEagerConstraints(array $models)
    {
        $parentKeys = array_map(fn ($model) => $model->getAttribute($this->parentKey), $models);
        $this->query->select("{$this->relatedTable}.*", "{$this->pivotTable}.{$this->foreignPivotKey} as pivot_foreign_key");
        $this->query->join(
            $this->pivotTable,
            "{$this->relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}",
        );
        $this->query->where("{$this->pivotTable}.{$this->foreignPivotKey}", 'IN', array_unique($parentKeys));
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, []);
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            // We use the pivot key from the result to map it back to the parent model.
            $pivotKey = $result->getAttribute('pivot_foreign_key');
            // We don't want the pivot key in the final related model attributes.
            $result->forgetAttribute('pivot_foreign_key');
            $dictionary[$pivotKey][] = $result;
        }
        return $dictionary;
    }

    /**
     * Attach a model to the parent.
     *
     * @param mixed $id A single ID or an array of IDs.
     * @return void
     */
    public function attach($id): void
    {
        $ids = is_array($id) ? $id : [$id];
        if (empty($ids)) {
            return;
        }

        $pdo = $this->query->getPdo();

        // Get current attached IDs to avoid duplicates
        $current = $this->getAttachedIds($ids);
        $toAttach = array_values(array_diff($ids, $current));

        if (empty($toAttach)) {
            return;
        }

        $records = [];
        foreach ($toAttach as $relatedId) {
            $records[] = [
                $this->foreignPivotKey => $this->parent->getKey(),
                $this->relatedPivotKey => $relatedId,
            ];
        }

        $this->newPivotQuery()->insert($records);
    }

    /**
     * Detach models from the parent.
     *
     * @param mixed|null $ids A single ID, an array of IDs, or null to detach all.
     * @return int The number of records deleted.
     */
    public function detach($ids = null): int
    {
        $pdo = $this->query->getPdo();
        $bindings = [$this->parent->getKey()];

        $query = "DELETE FROM {$this->pivotTable} WHERE {$this->foreignPivotKey} = ?";

        if (!is_null($ids)) {
            $ids = is_array($ids) ? $ids : [$ids];
            if (empty($ids)) {
                return 0;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query .= " AND {$this->relatedPivotKey} IN ({$placeholders})";
            $bindings = array_merge($bindings, $ids);
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Sync the intermediate tables with a list of IDs.
     *
     * @param \Core\Support\Collection|array $ids
     * @return array
     */
    public function sync($ids): array
    {
        if ($ids instanceof Collection) {
            $ids = $ids->all();
        }

        $changes = [
            'attached' => [],
            'detached' => [],
        ];

        $current = $this->getAttachedIds();

        $toDetach = array_values(array_diff($current, $ids));
        if (count($toDetach) > 0) {
            $this->detach($toDetach);
            $changes['detached'] = $toDetach;
        }

        $toAttach = array_values(array_diff($ids, $current));
        if (count($toAttach) > 0) {
            $this->attach($toAttach);
            $changes['attached'] = $toAttach;
        }

        return $changes;
    }

    /**
     * Get the IDs of the currently attached models.
     *
     * @param array|null $constrain An array of IDs to limit the search.
     * @return array
     */
    protected function getAttachedIds(array $constrain = null): array
    {
        $pdo = $this->query->getPdo();
        $bindings = [$this->parent->getKey()];

        $query = "SELECT {$this->relatedPivotKey} FROM {$this->pivotTable} WHERE {$this->foreignPivotKey} = ?";

        if ($constrain) {
            $placeholders = implode(',', array_fill(0, count($constrain), '?'));
            $query .= " AND {$this->relatedPivotKey} IN ({$placeholders})";
            $bindings = array_merge($bindings, $constrain);
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($bindings);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN, 0));
    }

    /**
     * Get a new query builder for the pivot table.
     *
     * @return \Core\ORM\QueryBuilder
     */
    protected function newPivotQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->parent::class))->table($this->pivotTable);
    }

    public function getSelectCountSql(): string
    {
        $parentTable = $this->parent->getTable();

        $joinClause = "INNER JOIN `{$this->pivotTable}` ON `{$this->relatedTable}`.`{$this->relatedKey}` = `{$this->pivotTable}`.`{$this->relatedPivotKey}`";
        $whereClause = "WHERE `{$this->pivotTable}`.`{$this->foreignPivotKey}` = `{$parentTable}`.`{$this->parentKey}`";

        // e.g., SELECT count(*) FROM tags INNER JOIN post_tag ON tags.id = post_tag.tag_id WHERE post_tag.post_id = posts.id
        $sql = "SELECT count(*) FROM `{$this->relatedTable}` {$joinClause} {$whereClause}";

        return $sql;
    }
}
