<?php

namespace Core\ORM;

use Core\ORM\Exceptions\ModelNotFoundException;
use Core\Support\Collection;
use Psr\Http\Message\ServerRequestInterface as Request;
use Swoole\Database\PDOProxy;
use Swoole\Database\PDOStatementProxy;

class QueryBuilder
{
    protected string $modelClass;
    protected ?Model $modelInstance = null;
    protected string $table;
    protected array $wheres = [];
    protected array $eagerLoad = [];
    protected array $joins = [];
    protected array $columns = ['*'];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $groups = [];
    protected array $removedScopes = [];
    protected bool $distinct = false;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Get the model instance for the query.
     *
     * @return Model
     */
    public function getModel(): Model
    {
        if ($this->modelInstance === null) {
            $this->modelInstance = new $this->modelClass();
        }
        return $this->modelInstance;
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(...$columns): self
    {
        $model = $this->getModel();
        $selectableColumns = $model->getSelectableColumns();

        if (!empty($selectableColumns)) {
            foreach ($columns as $column) {
                if (!in_array($column, $selectableColumns)) {
                    throw new \InvalidArgumentException(sprintf('Column [%s] is not selectable on model [%s].', $column, get_class($model)));
                }
            }
        }

        $this->columns = $columns;
        return $this;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->join($table, $first, $operator, $second, 'LEFT');
        return $this;
    }

    public function with(string|array $relations): self
    {
        $this->eagerLoad = $this->parseWithRelations(
            is_array($relations) ? $relations : func_get_args(),
        );
        return $this;
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param  string|array  $relations
     * @return $this
     */
    public function withCount($relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        if (empty($relations)) {
            return $this;
        }

        $model = $this->getModel();

        if ($this->columns === ['*']) {
            $this->columns = [$this->table . '.*'];
        }

        foreach ($relations as $relationName) {
            if (!method_exists($model, $relationName)) {
                throw new \BadMethodCallException(sprintf(
                    'Call to undefined relationship %s on model %s.',
                    $relationName,
                    get_class($model),
                ));
            }

            $relation = $model->{$relationName}();
            $subquery = $relation->getSelectCountSql();
            $this->columns[] = "({$subquery}) as `{$relationName}_count`";
        }

        return $this;
    }

    public function where($column, $operator = null, $value = null): self
    {
        if (is_string($column)) {
            $model = $this->getModel();
            $filterableColumns = $model->getFilterableColumns();

            if (!empty($filterableColumns) && !in_array($column, $filterableColumns)) {
                throw new \InvalidArgumentException(sprintf('Column [%s] is not filterable on model [%s].', $column, get_class($model)));
            }
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if (is_null($value)) {
            $operator = in_array(strtoupper($operator), ['!=', '<>']) ? 'IS NOT NULL' : 'IS NULL';
        }

        $this->wheres[] = compact('column', 'operator', 'value');
        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->where($column, 'IN', $values);
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->where($column, 'NOT IN', $values);
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function whereNull(string $column): self
    {
        return $this->where($column, 'IS NULL', null);
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function whereNotNull(string $column): self
    {
        return $this->where($column, 'IS NOT NULL', null);
    }

    /**
     * Remove a global scope from the query.
     *
     * @param  string  $scope
     * @return $this
     */
    public function withoutGlobalScope(string $scope): self
    {
        $this->removedScopes[] = $scope;
        return $this;
    }

    /**
     * Remove all or given global scopes from the query.
     *
     * @param  array|null  $scopes
     * @return $this
     */
    public function withoutGlobalScopes(?array $scopes = null): self
    {
        if (is_null($scopes)) {
            $scopes = array_keys($this->getModel()->getGlobalScopes());
        }

        $this->removedScopes = array_merge($this->removedScopes, $scopes);
        return $this;
    }

    public function applyGlobalScopes(): self
    {
        foreach ($this->getModel()->getGlobalScopes() as $identifier => $scope) {
            if (!in_array($identifier, $this->removedScopes)) {
                $scope->apply($this, $this->getModel());
            }
        }
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $model = $this->getModel();
        $sortableColumns = $model->getSortableColumns();

        if (!empty($sortableColumns) && !in_array($column, $sortableColumns)) {
            throw new \InvalidArgumentException(sprintf('Column [%s] is not sortable on model [%s].', $column, get_class($model)));
        }

        $this->orders[] = compact('column', 'direction');
        return $this;
    }

    public function limit(int $value): self
    {
        $this->limit = $value;
        return $this;
    }

    public function offset(int $value): self
    {
        $this->offset = $value;
        return $this;
    }

    public function find(int $id): ?Model
    {
        return $this->where((new $this->modelClass())->getKeyName(), '=', $id)->first();
    }

    /**
     * Find a model by its primary key or call a callback.
     *
     * @param  int  $id
     * @param  \Closure  $callback
     * @return mixed
     */
    public function findOr(int $id, \Closure $callback): mixed
    {
        return $this->find($id) ?? $callback();
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  int  $id
     * @return \Core\ORM\Model
     *
     * @throws \Core\ORM\Exceptions\ModelNotFoundException
     */
    public function findOrFail(int $id): Model
    {
        if (!is_null($result = $this->find($id))) {
            return $result;
        }
        throw (new ModelNotFoundException())->setModel($this->modelClass);
    }

    public function first(): ?Model
    {
        $models = (clone $this)->limit(1)->get();

        return $models->first();
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    public function firstOr(\Closure $callback): mixed
    {
        return $this->first() ?? $callback();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @return \Core\ORM\Model
     *
     * @throws \Core\ORM\Exceptions\ModelNotFoundException
     */
    public function firstOrFail(): Model
    {
        if (! is_null($result = $this->first())) {
            return $result;
        }
        throw (new ModelNotFoundException())->setModel($this->modelClass);
    }

    public function get(): Collection
    {
        $columns = implode(', ', $this->columns);
        $distinctClause = $this->distinct ? 'DISTINCT ' : '';
        $sql = "SELECT {$distinctClause}{$columns} FROM {$this->table}";
        [$whereClause, $whereBindings] = $this->buildWhereClause();

        $sql .= $this->buildJoinClause();
        $sql .= $whereClause;
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildOrderByClause();
        [$limitClause, $limitBindings] = $this->buildLimitClause();
        $sql .= $limitClause;

        $bindings = array_merge($whereBindings, $limitBindings);
        $stmt = $this->execute($sql, $bindings, 'read');
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $models = array_map([$this, 'hydrate'], $results);

        if (!empty($models) && !empty($this->eagerLoad)) {
            $this->eagerLoadRelations($models);
        }
        return new Collection($models);
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Core\ORM\Paginator
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): Paginator
    {
        $page = $page ?: (int) app(Request::class)->query($pageName, 1);

        $total = (clone $this)->count();

        $results = $this->forPage($page, $perPage)->get();

        return new Paginator($results, $total, $perPage, $page, [
            'path' => app(Request::class)->getUri()->getPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Constrain the query to the given page.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return $this
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function groupBy(...$columns): self
    {
        $model = $this->getModel();
        $groupableColumns = $model->getGroupableColumns();

        if (!empty($groupableColumns)) {
            foreach ($columns as $column) {
                if (!in_array($column, $groupableColumns)) {
                    throw new \InvalidArgumentException(sprintf('Column [%s] is not groupable on model [%s].', $column, get_class($model)));
                }
            }
        }

        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function count(string $columns = '*'): int
    {
        $sql = "SELECT COUNT({$columns}) FROM {$this->table}";
        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql .= $this->buildJoinClause();
        $sql .= $whereClause;

        $stmt = $this->execute($sql, $bindings, 'read');

        return (int) $stmt->fetchColumn();
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum(string $column): mixed
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg(string $column): mixed
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  string  $column
     * @return mixed
     */
    protected function aggregate(string $function, string $column): mixed
    {
        $sql = "SELECT {$function}({$column}) FROM {$this->table}";
        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql .= $this->buildJoinClause();
        $sql .= $whereClause;
        $sql .= $this->buildGroupByClause();

        $stmt = $this->execute($sql, $bindings, 'read');
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : null;
    }

    public function exists(): bool
    {
        $sql = "SELECT EXISTS(SELECT 1 FROM {$this->table}";
        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql .= $this->buildJoinClause();
        $sql .= $whereClause;
        $sql .= ' LIMIT 1)';

        $stmt = $this->execute($sql, $bindings, 'read');
        return (bool) $stmt->fetchColumn();
    }

    public function insertGetId(array $attributes): ?int
    {
        $columns = implode(', ', array_keys($attributes));
        $values = [];
        $bindings = [];

        foreach ($attributes as $value) {
            if ($value instanceof RawExpression) {
                $values[] = $value->getValue();
            } else {
                $values[] = '?';
                $bindings[] = $value;
            }
        }
        $placeholders = implode(', ', $values);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $this->execute($sql, $bindings, 'write');
        $pdo = $this->getConnection('write');

        $id = $pdo->lastInsertId();
        return $id ? (int)$id : null;
    }

    /**
     * Insert new records into the database.
     *
     * @param  array  $values An array of arrays for multiple records, or a single array for one record.
     * @return bool
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = implode(', ', array_keys(reset($values)));

        $bindings = [];
        $placeholders = [];

        foreach ($values as $record) {
            $rowPlaceholders = [];
            foreach ($record as $value) {
                if ($value instanceof RawExpression) {
                    $rowPlaceholders[] = $value->getValue();
                } else {
                    $rowPlaceholders[] = '?';
                    $bindings[] = $value;
                }
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES " . implode(', ', $placeholders);

        return $this->execute($sql, $bindings, 'write')->rowCount() > 0;
    }

    public function update(array $attributes): int
    {
        $setParts = [];
        $bindings = [];

        foreach ($attributes as $column => $value) {
            if ($value instanceof RawExpression) {
                $setParts[] = "{$column} = " . $value->getValue();
            } else {
                $setParts[] = "{$column} = ?";
                $bindings[] = $value;
            }
        }
        $setClause = implode(', ', $setParts);
        [$whereClause, $whereBindings] = $this->buildWhereClause();

        $sql = "UPDATE {$this->table} SET {$setClause}" . $whereClause;

        $allBindings = array_merge($bindings, $whereBindings);

        return $this->execute($sql, $allBindings, 'write')->rowCount();
    }

    public function delete(): bool
    {
        $model = $this->getModel();
        if ($model->usesSoftDeletes()) {
            return $this->performSoftDelete();
        }

        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql = "DELETE FROM {$this->table}" . $whereClause;

        return $this->execute($sql, $bindings, 'write')->rowCount() > 0;
    }

    protected function buildWhereClause(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }

        $sql = ' WHERE ';
        $bindings = [];
        $conditions = [];

        foreach ($this->wheres as $where) {
            $operator = strtoupper(trim($where['operator']));

            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $conditions[] = "{$where['column']} {$operator}";
                continue;
            }

            if ($operator === 'IN' || $operator === 'NOT IN') {
                if (!is_array($where['value'])) {
                    throw new \InvalidArgumentException("The value for an '{$operator}' clause must be an array.");
                }
                if (empty($where['value'])) {
                    $conditions[] = ($operator === 'NOT IN') ? '1=1' : '1=0';
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($where['value']), '?'));
                $conditions[] = "{$where['column']} {$operator} ({$placeholders})";
                $bindings = array_merge($bindings, array_values($where['value']));
            } elseif ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
                if (!is_array($where['value']) || count($where['value']) !== 2) {
                    throw new \InvalidArgumentException("The value for a '{$operator}' clause must be an array with two elements.");
                }
                $conditions[] = "{$where['column']} {$operator} ? AND ?";
                $bindings = array_merge($bindings, array_values($where['value']));
            } else {
                if ($where['value'] instanceof RawExpression) {
                    $conditions[] = "{$where['column']} {$where['operator']} " . $where['value']->getValue();
                } else {
                    $conditions[] = "{$where['column']} {$where['operator']} ?";
                    $bindings[] = $where['value'];
                }
            }
        }

        $sql .= implode(' AND ', $conditions);

        return [$sql, $bindings];
    }

    protected function buildJoinClause(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        return $sql;
    }

    protected function buildOrderByClause(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $clauses = array_map(function ($order) {
            return "{$order['column']} {$order['direction']}";
        }, $this->orders);

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    protected function buildGroupByClause(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groups);
    }

    protected function buildLimitClause(): array
    {
        $sql = '';
        $bindings = [];

        if (!is_null($this->limit)) {
            $sql .= ' LIMIT ?';
            $bindings[] = $this->limit;
        }

        if (!is_null($this->offset)) {
            if (is_null($this->limit)) {
                $sql .= ' LIMIT ?';
                $bindings[] = 18446744073709551615;
            }
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offset;
        }

        return [$sql, $bindings];
    }

    protected function hydrate(array $attributes): Model
    {
        /** @var Model $model */
        $model = new $this->modelClass();
        return $model->newFromBuilder($attributes);
    }

    /**
     * Eager load relationships on a collection of models.
     * This is the public entry point for "lazy eager loading".
     *
     * @param  array  $models
     * @param  string|array  $relations
     * @return void
     */
    public function loadRelations(array $models, $relations): void
    {
        if (empty($models)) {
            return;
        }

        $relations = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);
        $this->eagerLoadLevel(new Collection($models), $relations);
    }

    /**
     * Load a relationship count on a collection of models.
     *
     * @param  \Core\Support\Collection  $models
     * @param  string|array  $relations
     * @return void
     */
    public function loadCount(Collection $models, $relations): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $relations = is_string($relations) ? func_get_args() : $relations;
        if (is_string(reset($relations))) {
            $relations = array_slice($relations, 1);
        }

        foreach ($relations as $name => $constraints) {
            $relationName = is_numeric($name) ? $constraints : $name;

            $relation = $models->first()->{$relationName}();

            if ($relation instanceof Relations\MorphTo) {
                throw new \LogicException('loadCount() is not supported for MorphTo relationships.');
            }

            $query = $relation->getQuery();
            $relation->addEagerConstraints($models->all());

            $foreignKey = $relation instanceof Relations\BelongsToMany
                ? "{$relation->getPivotTable()}.{$relation->getForeignPivotKey()}"
                : $relation->getQualifiedForeignKeyName();

            $results = $query->selectRaw("{$foreignKey}, count(*) as aggregate")
                ->groupBy($foreignKey)
                ->pluck('aggregate', $foreignKey);

            $parentKeyName = $relation instanceof Relations\BelongsToMany
                ? $relation->getParentKeyName()
                : $relation->getLocalKeyName();

            foreach ($models as $model) {
                $model->setAttribute($relationName . '_count', $results[$model->getAttribute($parentKeyName)] ?? 0);
            }
        }
    }

    protected function eagerLoadRelations(array $models): void
    {
        $this->eagerLoadLevel(new Collection($models), $this->eagerLoad);
    }

    /**
     * Eagerly load the relationships on a set of models.
     * This method is recursive to handle nested relationships.
     *
     * @param  array  $models
     * @param  array  $relations
     * @return void
     */
    protected function eagerLoadLevel(Collection $models, array $relations): void
    {
        if (empty($models)) {
            return;
        }

        foreach ($relations as $relationName => $relationData) {
            /** @var \Core\ORM\Relations\Relation $relation */
            $relation = $models->first()->{$relationName}();

            if ($relation instanceof Relations\MorphTo) {
                $this->eagerLoadMorphTo($relation, $models, $relationName, $relationData);
            } else {
                $this->eagerLoadStandardRelation($relation, $models, $relationName, $relationData);
            }
        }
    }

    protected function eagerLoadStandardRelation(\Core\ORM\Relations\Relation $relation, Collection $models, string $relationName, array $relationData): void
    {
        if (is_callable($relationData['constraints'])) {
            $relationData['constraints']($relation->getQuery());
        }

        $relation->addEagerConstraints($models->all());

        $results = $relation->get();

        $relation->match($models->all(), $results->all(), $relationName);

        if (!empty($relationData['nested'])) {
            $this->eagerLoadLevel($this->collectRelatedModels($models, $relationName), $relationData['nested']);
        }
    }

    protected function eagerLoadMorphTo(\Core\ORM\Relations\MorphTo $relation, Collection $models, string $relationName, array $relationData): void
    {
        $morphable = $models->groupBy($relation->getMorphType());

        $allResults = new Collection();

        foreach ($morphable as $type => $modelGroup) {
            if (!$type) {
                continue;
            }

            $instance = new $type();
            $ownerKeyName = $instance->getKeyName();
            $foreignKeyName = $relation->getForeignKey();

            $ownerKeys = $modelGroup->pluck($foreignKeyName)->filter()->unique()->all();

            if (empty($ownerKeys)) {
                continue;
            }

            $query = $instance->newQuery()->whereIn($ownerKeyName, $ownerKeys);

            if (is_callable($relationData['constraints'])) {
                $relationData['constraints']($query);
            }

            $allResults = $allResults->merge($query->get());
        }

        $dictionary = $allResults->keyBy(function ($result) {
            return get_class($result) . ':' . $result->getKey();
        });

        foreach ($models as $model) {
            $morphType = $model->{$relation->getMorphType()};
            $ownerKey = $model->{$relation->getForeignKey()};
            $key = $morphType . ':' . $ownerKey;

            if ($morphType && $ownerKey && isset($dictionary[$key])) {
                $model->setRelation($relationName, $dictionary[$key]);
            }
        }

        if (!empty($relationData['nested'])) {
            $this->eagerLoadLevel($this->collectRelatedModels($models, $relationName), $relationData['nested']);
        }
    }

    protected function parseWithRelations(array $relations): array
    {
        $parsed = [];

        if ($relations instanceof Collection) {
            $relations = $relations->all();
        }

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $segments = explode('.', $name);
            $container = &$parsed;

            foreach ($segments as $i => $segment) {
                if (!isset($container[$segment])) {
                    $container[$segment] = ['nested' => [], 'constraints' => null];
                }

                if ($i === count($segments) - 1 && is_callable($constraints)) {
                    $container[$segment]['constraints'] = $constraints;
                }

                $container = &$container[$segment]['nested'];
            }
        }
        return $parsed;
    }

    private function collectRelatedModels(Collection $models, string $relationName): Collection
    {
        $related = new Collection();

        foreach ($models as $model) {
            $loadedRelation = $model->getAttribute($relationName);

            if ($loadedRelation) {
                if ($loadedRelation instanceof Model) {
                    $related->push($loadedRelation);
                } elseif ($loadedRelation instanceof Collection || is_array($loadedRelation)) {
                    $related = $related->merge($loadedRelation);
                }
            }
        }
        return $related;
    }

    /**
     * @deprecated Use getConnection('write') instead. This method is for backward compatibility.
     * @return \PDO|PDOProxy
     */
    public function getPdo(): \PDO|PDOProxy
    {
        return $this->getConnection('write');
    }

    /**
     * Get the PDO connection instance for a specific type.
     */
    protected function getConnection(string $type): \PDO|PDOProxy
    {
        return app(Connection::class)->connection(null, $type);
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return array
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $columnsToSelect = [$column];
        if ($key !== null) {
            $columnsToSelect[] = $key;
        }
        $columns = implode(', ', $columnsToSelect);

        $sql = "SELECT {$columns} FROM {$this->table}";
        [$whereClause, $whereBindings] = $this->buildWhereClause();

        $sql .= $this->buildJoinClause();
        $sql .= $whereClause;
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildOrderByClause();

        [$limitClause, $limitBindings] = $this->buildLimitClause();
        $sql .= $limitClause;

        $stmt = $this->execute($sql, array_merge($whereBindings, $limitBindings), 'read');

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($results, $column, $key);
    }

    /**
     * Begin querying a model with trashed records.
     *
     * @return $this
     */
    public function withTrashed(): self
    {
        $model = $this->getModel();
        if ($model->usesSoftDeletes()) {
            $column = $model->getQualifiedDeletedAtColumn();

            $this->wheres = array_values(array_filter($this->wheres, function ($where) use ($column) {
                return !($where['column'] === $column && strtoupper($where['operator']) === 'IS NULL');
            }));
        }
        return $this;
    }

    /**
     * Begin querying a model with only trashed records.
     *
     * @return $this
     */
    public function onlyTrashed(): self
    {
        $model = $this->getModel();
        if ($model->usesSoftDeletes()) {
            $this->withTrashed()->whereNotNull($model->getQualifiedDeletedAtColumn());
        }
        return $this;
    }

    /**
     * Run a raw, hard delete query on the table.
     */
    public function forceDelete(): int
    {
        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql = "DELETE FROM {$this->table}" . $whereClause;

        return $this->execute($sql, $bindings, 'write')->rowCount();
    }

    /**
     * Restore soft-deleted records.
     *
     * @return bool
     */
    public function restore(): bool
    {
        $model = $this->getModel();
        if (!$model->usesSoftDeletes()) {
            return false;
        }

        return $this->update([$model->getDeletedAtColumn() => null]) > 0;
    }

    /**
     * Perform a soft-delete on the records.
     *
     * @return bool
     */
    protected function performSoftDelete(): bool
    {
        $model = $this->getModel();
        $column = $model->getDeletedAtColumn();
        $time = date('Y-m-d H:i:s');

        return $this->update([$column => $time]) > 0;
    }

    /**
     * Handle dynamic method calls into the query builder.
     * This is used for local scopes.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return $this
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        $model = $this->getModel();

        if (method_exists($model, $scopeMethod)) {
            $model->{$scopeMethod}($this, ...$parameters);
            return $this;
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }

    public function decrement(string $column, int $amount = 1): bool
    {
        return $this->update([
            $column => new RawExpression("`$column` - $amount"),
        ]);
    }

    public function increment(string $column, int $amount = 1): bool
    {
        return $this->update([
            $column => new RawExpression("`$column` + $amount"),
        ]);
    }

    /**
     * Execute a statement with bindings.
     *
     * @param string $sql
     * @param array $bindings
     * @param string $connectionType 'read' or 'write'
     * @return \PDOStatement|PDOStatementProxy
     */
    private function execute(string $sql, array $bindings, string $connectionType): \PDOStatement|PDOStatementProxy
    {
        $pdo = $this->getConnection($connectionType);
        $stmt = $pdo->prepare($sql);

        if (!empty($bindings)) {
            foreach ($bindings as $key => $value) {
                $paramType = \PDO::PARAM_STR;
                if (is_array($value)) {
                    $value = json_encode($value);
                    $paramType = \PDO::PARAM_STR;
                } elseif (is_int($value)) {
                    $paramType = \PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $value = (int) $value; // Convert true to 1, false to 0
                    $paramType = \PDO::PARAM_INT;
                } elseif (is_null($value)) {
                    $paramType = \PDO::PARAM_NULL;
                }
                $stmt->bindValue($key + 1, $value, $paramType);
            }
            $stmt->execute();
        } else {
            $stmt->execute();
        }

        return $stmt;
    }
}
