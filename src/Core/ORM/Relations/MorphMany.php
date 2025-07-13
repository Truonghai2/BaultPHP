<?php

namespace Core\ORM\Relations;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class MorphMany extends HasMany
{
    /**
     * The name of the "type" column.
     * @var string
     */
    protected string $morphType;

    /**
     * The class name of the parent model.
     * @var string
     */
    protected string $morphClass;

    public function __construct(QueryBuilder $query, Model $parent, string $morphType, string $foreignKey, string $localKey)
    {
        $this->morphType = $morphType;
        $this->morphClass = $parent->getMorphClass();

        parent::__construct($query, $parent, $foreignKey, $localKey);

        $this->query->where($this->morphType, '=', $this->morphClass);
    }

    public function getSelectCountSql(): string
    {
        $relatedTable = $this->query->getModel()->getTable();
        $parentTable = $this->parent->getTable();

        $sql = "SELECT count(*) FROM `{$relatedTable}` WHERE `{$relatedTable}`.`{$this->foreignKey}` = `{$parentTable}`.`{$this->localKey}`";
        $sql .= " AND `{$relatedTable}`.`{$this->morphType}` = '{$this->morphClass}'";

        return $sql;
    }
}