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
     */
    public function attach($id): void
    {
        $ids = is_array($id) ? $id : [$id];
        if (empty($ids)) {
            return;
        }

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
                $this->morphType => $this->morphClass,
            ];
        }

        $this->newPivotQuery()->insert($records);
    }
}
