<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class MorphedByMany extends MorphToMany
{
    /**
     * We are inverting the relationship, so we need to swap the keys.
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $pivotTable,
        string $foreignPivotKey, // e.g., tag_id
        string $relatedPivotKey, // e.g., post_id
        string $parentKey,       // e.g., id on tags table
        string $relatedKey,      // e.g., id on posts table
        string $morphType,
    ) {
        parent::__construct($query, $parent, $pivotTable, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $morphType);
    }
}
