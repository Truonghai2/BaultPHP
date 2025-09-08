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
    protected array $pivotColumns = [];
    protected array $pivotWheres = [];
    protected array $pivotOrders = [];
    protected ?string $pivotCreatedAt = null;
    protected ?string $pivotUpdatedAt = null;
    protected bool $usingTimestamps = false;
    protected bool $usingPivotSoftDeletes = false;
    protected string $pivotDeletedAt = 'deleted_at';
    protected bool $withPivotTrashed = false;

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
        $pivotColumns = $this->getPivotColumns();
        $this->query->select(array_merge(["{$this->relatedTable}.*"], $pivotColumns));

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

        if ($this->usingPivotSoftDeletes && !$this->withPivotTrashed) {
            $this->query->whereNull("{$this->pivotTable}.{$this->pivotDeletedAt}");
        }

        $this->applyPivotWheres();
        $this->applyPivotOrders();

        return $this->query->get();
    }

    public function addEagerConstraints(array $models)
    {
        $parentKeys = array_map(fn ($model) => $model->getAttribute($this->parentKey), $models);
        $pivotColumns = $this->getPivotColumns();
        $this->query->select(array_merge(["{$this->relatedTable}.*"], $pivotColumns));

        $this->query->join(
            $this->pivotTable,
            "{$this->relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}",
        );
        $this->query->where("{$this->pivotTable}.{$this->foreignPivotKey}", 'IN', array_unique($parentKeys));

        if ($this->usingPivotSoftDeletes && !$this->withPivotTrashed) {
            $this->query->whereNull("{$this->pivotTable}.{$this->pivotDeletedAt}");
        }

        $this->applyPivotWheres();
        $this->applyPivotOrders();
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
            $pivotKey = $result->getAttribute('pivot_' . $this->foreignPivotKey);

            $this->attachPivot($result);

            $dictionary[$pivotKey][] = $result;
        }
        return $dictionary;
    }

    /**
     * Attach a model to the parent.
     *
     * @param mixed $ids A single ID or an array of IDs.
     * @param array $attributes Additional data for the pivot table.
     * @return void
     */
    public function attach($ids, array $attributes = []): void
    {
        if ($this->usingTimestamps) {
            $time = date('Y-m-d H:i:s');
            $attributes[$this->pivotCreatedAt] = $time;
            $attributes[$this->pivotUpdatedAt] = $time;
        }

        $ids = is_array($ids) ? $ids : [$ids];
        if (empty($ids)) {
            return;
        }

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
            ] + $attributes;
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
        $query = $this->newPivotQuery()->where($this->foreignPivotKey, '=', $this->parent->getKey());

        if (!is_null($ids)) {
            $ids = is_array($ids) ? $ids : [$ids];
            if (empty($ids)) {
                return 0;
            }
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        // If we are soft deleting, we will update the deleted_at timestamp.
        if ($this->usingPivotSoftDeletes) {
            $attributes = [$this->pivotDeletedAt => date('Y-m-d H:i:s')];

            if ($this->usingTimestamps) {
                $attributes[$this->pivotUpdatedAt] = $attributes[$this->pivotDeletedAt];
            }

            return $query->update($attributes);
        }

        return (int) $query->forceDelete();
    }

    /**
     * Sync the intermediate tables with a list of IDs, handling soft deletes if necessary.
     *
     * @param \Core\Support\Collection|array $ids
     * @return array
     */
    public function sync(Collection|array $ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [], 'detached' => [], 'updated' => [],
        ];

        $syncData = $this->formatSyncIds($ids);
        $newIds = array_keys($syncData);

        $currentPivot = $this->rawPivotQuery()
            ->where($this->foreignPivotKey, $this->parent->getKey())
            ->get()
            ->keyBy($this->relatedPivotKey);

        if ($detaching) {
            $toDetach = $currentPivot->filter(function ($record) use ($newIds) {
                $isSoftDeleted = $this->usingPivotSoftDeletes && !is_null($record->{$this->pivotDeletedAt});
                return !$isSoftDeleted && !in_array($record->{$this->relatedPivotKey}, $newIds);
            })->keys()->all();

            if (count($toDetach) > 0) {
                $this->detach($toDetach);
                $changes['detached'] = array_values($toDetach);
            }
        }

        foreach ($syncData as $id => $attributes) {
            $record = $currentPivot->get($id);

            if (!$record) {
                $this->attach($id, $attributes);
                $changes['attached'][] = $id;
            } else {
                $updateAttributes = $attributes;
                $isTrashed = $this->usingPivotSoftDeletes && !is_null($record->{$this->pivotDeletedAt});

                if ($isTrashed) {
                    $updateAttributes[$this->pivotDeletedAt] = null;
                }

                if (count($updateAttributes) > 0 || $isTrashed) {
                    $this->updateExistingPivot($id, $updateAttributes, true);
                    $isTrashed ? $changes['attached'][] = $id : $changes['updated'][] = $id;
                }
            }
        }

        return $changes;
    }

    /**
     * Sync the intermediate tables with a list of IDs and their pivot data.
     * This is an alias for the sync() method, making the intent clearer.
     *
     * @param  \Core\Support\Collection|array  $ids
     * @param  bool  $detaching
     * @return array
     */
    public function syncWithPivotValues(Collection|array $ids, bool $detaching = true): array
    {
        return $this->sync($ids, $detaching);
    }

    /**
     * Update an existing pivot record.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $includeTrashed
     * @return int
     */
    public function updateExistingPivot($id, array $attributes, bool $includeTrashed = false): int
    {
        if ($this->usingTimestamps) {
            $attributes[$this->pivotUpdatedAt] = date('Y-m-d H:i:s');
        }

        $query = $this->newPivotQuery();

        if ($includeTrashed && $this->usingPivotSoftDeletes) {
            $query = $this->rawPivotQuery();
        }

        return $query->where($this->foreignPivotKey, $this->parent->getKey())
            ->where($this->relatedPivotKey, $id)
            ->update($attributes);
    }

    /**
     * Sync the intermediate tables with a list of IDs without detaching.
     * This method will only attach new IDs and will not remove existing ones.
     *
     * @param  \Core\Support\Collection|array $ids
     * @return array
     */
    public function syncWithoutDetaching(Collection|array $ids): array
    {
        return $this->sync($ids, false);
    }

    /**
     * Indicate that the pivot table has creation and update timestamps.
     *
     * @param  string|null  $createdAt
     * @param  string|null  $updatedAt
     * @return $this
     */
    public function withTimestamps(?string $createdAt = null, ?string $updatedAt = null): self
    {
        $this->usingTimestamps = true;
        $this->pivotCreatedAt = $createdAt ?? 'created_at';
        $this->pivotUpdatedAt = $updatedAt ?? 'updated_at';

        return $this->withPivot([$this->pivotCreatedAt, $this->pivotUpdatedAt]);
    }

    /**
     * Indicate that the pivot table has soft deletes.
     *
     * @param  string  $column
     * @return $this
     */
    public function withSoftDeletes(string $column = 'deleted_at'): self
    {
        $this->usingPivotSoftDeletes = true;
        $this->pivotDeletedAt = $column;

        return $this->withPivot([$column]);
    }

    /**
     * Include soft-deleted pivot records in the query results.
     *
     * @return $this
     */
    public function withPivotTrashed(): self
    {
        $this->withPivotTrashed = true;
        return $this;
    }

    /**
     * Specify additional pivot table columns to retrieve.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function withPivot(array $columns): self
    {
        $this->pivotColumns = array_unique(array_merge($this->pivotColumns, $columns));
        return $this;
    }

    /**
     * Add a "where" clause to the pivot table query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function wherePivot(string $column, ?string $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->pivotWheres[] = compact('column', 'operator', 'value');

        return $this;
    }

    /**
     * Add a "where in" clause to the pivot table query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function wherePivotIn(string $column, array $values): self
    {
        return $this->wherePivot($column, 'IN', $values);
    }

    /**
     * Add an "order by" clause to the pivot table query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderByPivot(string $column, string $direction = 'asc'): self
    {
        $this->pivotOrders[] = compact('column', 'direction');

        return $this;
    }

    /**
     * Format the sync list so that it is keyed by ID.
     *
     * @param  array|Collection  $ids
     * @return array
     */
    protected function formatSyncIds($ids): array
    {
        if ($ids instanceof Collection) {
            $ids = $ids->all();
        }

        $syncData = [];
        foreach ((array) $ids as $key => $value) {
            $syncData[is_array($value) ? $key : $value] = is_array($value) ? $value : [];
        }
        return $syncData;
    }

    /**
     * Get the IDs of the currently attached models.
     *
     * @param array|null $constrain An array of IDs to limit the search.
     * @return array
     */
    protected function getAttachedIds(array $constrain = null): array
    {
        $query = $this->newPivotQuery()
                      ->where($this->foreignPivotKey, '=', $this->parent->getKey());

        if ($constrain) {
            $query->whereIn($this->relatedPivotKey, $constrain);
        }

        $ids = $query->pluck($this->relatedPivotKey);
        return array_map('intval', $ids);
    }

    /**
     * Get the select columns for the pivot table.
     *
     * @return array
     */
    protected function getPivotColumns(): array
    {
        $columns = [$this->pivotTable . '.' . $this->foreignPivotKey . ' as pivot_' . $this->foreignPivotKey];

        foreach ($this->pivotColumns as $column) {
            $columns[] = $this->pivotTable . '.' . $column . ' as pivot_' . $column;
        }

        return array_unique($columns);
    }

    /**
     * Hydrate the pivot relationship on the model.
     *
     * @param  \Core\ORM\Model  $model
     * @return void
     */
    protected function attachPivot(Model $model): void
    {
        $pivotAttributes = [];

        foreach ($model->getAttributes() as $key => $value) {
            if (str_starts_with($key, 'pivot_')) {
                $pivotAttributes[substr($key, 6)] = $value;
                $model->forgetAttribute($key);
            }
        }

        $model->setRelation('pivot', new Pivot($pivotAttributes));
    }

    /**
     * Apply the pivot where clauses to the query.
     *
     * @return void
     */
    protected function applyPivotWheres(): void
    {
        foreach ($this->pivotWheres as $where) {
            $this->query->where(
                "{$this->pivotTable}.{$where['column']}",
                $where['operator'],
                $where['value'],
            );
        }
    }

    /**
     * Apply the pivot order by clauses to the query.
     *
     * @return void
     */
    protected function applyPivotOrders(): void
    {
        foreach ($this->pivotOrders as $order) {
            $this->query->orderBy(
                "{$this->pivotTable}.{$order['column']}",
                $order['direction'],
            );
        }
    }

    /**
     * Get a new query builder for the pivot table.
     *
     * @return \Core\ORM\QueryBuilder
     */
    protected function newPivotQuery(): QueryBuilder
    {
        $query = $this->rawPivotQuery();

        if ($this->usingPivotSoftDeletes && !$this->withPivotTrashed) {
            $query->whereNull("{$this->pivotTable}.{$this->pivotDeletedAt}");
        }

        return $query;
    }

    /**
     * Get a new query builder for the pivot table without any constraints.
     *
     * @return \Core\ORM\QueryBuilder
     */
    protected function rawPivotQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->parent::class))->table($this->pivotTable);
    }

    public function getSelectCountSql(): string
    {
        $parentTable = $this->parent->getTable();

        $joinClause = "INNER JOIN `{$this->pivotTable}` ON `{$this->relatedTable}`.`{$this->relatedKey}` = `{$this->pivotTable}`.`{$this->relatedPivotKey}`";
        $whereClause = "WHERE `{$this->pivotTable}`.`{$this->foreignPivotKey}` = `{$parentTable}`.`{$this->parentKey}`";

        $sql = "SELECT count(*) FROM `{$this->relatedTable}` {$joinClause} {$whereClause}";

        return $sql;
    }

    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    public function getForeignPivotKey(): string
    {
        return $this->foreignPivotKey;
    }

    public function getParentKeyName(): string
    {
        return $this->parentKey;
    }
}
