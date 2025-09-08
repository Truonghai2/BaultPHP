<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class MorphToMany extends BelongsToMany
{
    /**
     * The type of the polymorphic relation.
     * @var string
     */
    protected string $morphType;

    /**
     * The class name of the parent model.
     * @var string
     */
    protected string $morphClass;

    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        string $morphType,
    ) {
        $this->morphType = $morphType;
        $this->morphClass = $parent->getMorphClass();

        parent::__construct($query, $parent, $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);

        $this->query->where("{$this->pivotTable}.{$this->morphType}", '=', $this->morphClass);
    }

    /**
     * Attach a model to the parent.
     * Overrides the parent method to add the morph type.
     *
     * @param mixed $ids
     * @param array $attributes
     * @return void
     */
    public function attach($ids, array $attributes = []): void
    {
        // Add the morph type to the attributes for the pivot table.
        $attributes[$this->morphType] = $this->morphClass;

        parent::attach($ids, $attributes);
    }

    /**
     * Sync the intermediate tables with a list of IDs.
     * Overrides the parent method to add the morph type.
     *
     * @param \Core\Support\Collection|array $ids
     * @param bool $detaching
     * @return array
     */
    public function sync(Collection|array $ids, bool $detaching = true): array
    {
        $syncData = $this->formatSyncIds($ids);

        foreach ($syncData as $id => &$attributes) {
            $attributes[$this->morphType] = $this->morphClass;
        }

        return parent::sync($syncData, $detaching);
    }

    /**
     * Get a new query builder for the pivot table.
     */
    protected function newPivotQuery(): QueryBuilder
    {
        return parent::newPivotQuery()->where($this->morphType, '=', $this->morphClass);
    }

    /**
     * Get the SQL for a sub-select to count the number of related models.
     * Overrides the parent method to add the morph type constraint.
     */
    public function getSelectCountSql(): string
    {
        $parentTable = $this->parent->getTable();
        $joinClause = "INNER JOIN `{$this->pivotTable}` ON `{$this->relatedTable}`.`{$this->relatedKey}` = `{$this->pivotTable}`.`{$this->relatedPivotKey}`";
        $whereClause = "WHERE `{$this->pivotTable}`.`{$this->foreignPivotKey}` = `{$parentTable}`.`{$this->parentKey}`";
        $whereClause .= " AND `{$this->pivotTable}`.`{$this->morphType}` = '{$this->morphClass}'";
        $sql = "SELECT count(*) FROM `{$this->relatedTable}` {$joinClause} {$whereClause}";
        return $sql;
    }
}
