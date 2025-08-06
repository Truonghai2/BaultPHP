<?php

namespace Core\ORM;

use Core\ORM\Exceptions\ModelNotFoundException;
use Core\Support\Collection;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class QueryBuilder
{
    protected string $modelClass;
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
        return new $this->modelClass();
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

        // If a selectable whitelist is defined, validate every column.
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

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        // Simply call the main join method with the type 'LEFT'
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

    public function where($column, string $operator = null, $value = null): self
    {
        // If the column is a Closure, it's a nested where clause, so we don't validate the column name.
        // This part of the logic needs to be implemented if you support where(Closure).
        // For now, we focus on string-based columns.
        if (is_string($column)) {
            $model = $this->getModel();
            $filterableColumns = $model->getFilterableColumns();

            // If a filterable whitelist is defined, validate the column.
            if (!empty($filterableColumns) && !in_array($column, $filterableColumns)) {
                throw new \InvalidArgumentException(sprintf('Column [%s] is not filterable on model [%s].', $column, get_class($model)));
            }
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        // If the value is null, we will automatically use the 'IS' or 'IS NOT' operator.
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

        // Nếu danh sách sortable được định nghĩa và cột không có trong đó, hãy ném ra một ngoại lệ.
        // Điều này ngăn chặn SQL injection qua tên cột.
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
        // By cloning the query and using limit(1)->get(), we reuse the existing
        // query building and eager loading logic, reducing code duplication
        // and ensuring consistent behavior.
        $models = (clone $this)->limit(1)->get();

        // Sử dụng phương thức first() của Collection để code dễ đọc hơn.
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
        $sql = "SELECT {$columns} FROM {$this->table}";
        [$whereClause, $whereBindings] = $this->buildWhereClause();

        $sql .= $this->buildJoinClause();
        $sql .= $whereClause;
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildOrderByClause();
        [$limitClause, $limitBindings] = $this->buildLimitClause();
        $sql .= $limitClause;

        $bindings = array_merge($whereBindings, $limitBindings);
        $stmt = $this->getConnection('read')->prepare($sql);
        $stmt->execute($bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        // Determine the current page number from the request if not provided.
        $page = $page ?: (int) app(Request::class)->query($pageName, 1);

        // Clone the query builder to get the total count without affecting the main query's limit/offset.
        $total = (clone $this)->count();

        // Get the results for the current page.
        $results = $this->forPage($page, $perPage)->get();

        // Create and return the paginator instance.
        return new Paginator($results, $total, $perPage, $page, [
            'path' => app(ServerRequestInterface::class)->getUri()->getPath(),
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

        // If a groupable whitelist is defined, validate every column.
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

        $stmt = $this->getConnection('read')->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    public function exists(): bool
    {
        // Re-use the `first()` method logic but just check for existence.
        // This is more efficient than `count() > 0` as it can stop at the first record.
        $query = clone $this; // Clone to not affect the original query object
        $query->select((new $this->modelClass())->getKeyName());

        return (bool) $query->first();
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

        $pdo = $this->getConnection('write');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);

        $id = $pdo->lastInsertId();
        return $id ? (int)$id : null;
    }

    public function update(array $attributes): bool
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

        $stmt = $this->getConnection('write')->prepare($sql);
        return $stmt->execute(array_merge($bindings, $whereBindings));
    }

    public function delete(): bool
    {
        $model = $this->getModel();
        if ($model->usesSoftDeletes()) {
            return $this->performSoftDelete();
        }

        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql = "DELETE FROM {$this->table}" . $whereClause;

        $stmt = $this->getConnection('write')->prepare($sql);
        return $stmt->execute($bindings);
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

            // Handle IS NULL and IS NOT NULL which don't have bindings
            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $conditions[] = "{$where['column']} {$operator}";
                continue; // Move to the next where clause
            }

            // Handle IN and NOT IN
            if ($operator === 'IN' || $operator === 'NOT IN') {
                if (!is_array($where['value'])) {
                    throw new \InvalidArgumentException("The value for an '{$operator}' clause must be an array.");
                }
                if (empty($where['value'])) {
                    // Handle cases like `WHERE id IN ()` which is invalid SQL.
                    // `WHERE 1=0` for `IN` and `WHERE 1=1` for `NOT IN` are safe fallbacks.
                    $conditions[] = ($operator === 'NOT IN') ? '1=1' : '1=0';
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($where['value']), '?'));
                $conditions[] = "{$where['column']} {$operator} ({$placeholders})";
                $bindings = array_merge($bindings, array_values($where['value']));
            }
            // Handle BETWEEN and NOT BETWEEN
            elseif ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
                if (!is_array($where['value']) || count($where['value']) !== 2) {
                    throw new \InvalidArgumentException("The value for a '{$operator}' clause must be an array with two elements.");
                }
                $conditions[] = "{$where['column']} {$operator} ? AND ?";
                $bindings = array_merge($bindings, array_values($where['value']));
            }
            // Handle standard operators (including LIKE)
            else {
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
            // LIMIT must be present for OFFSET to work in most SQL dialects
            if (is_null($this->limit)) {
                // Set a very large number for limit if only offset is used
                $sql .= ' LIMIT ?';
                $bindings[] = 18446744073709551615; // Max value for BIGINT UNSIGNED
            }
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offset;
        }

        // PDO requires integer bindings for LIMIT/OFFSET to be treated as integers, not strings.
        // However, `execute` with an array of values generally handles this correctly.
        // If issues arise, a manual `bindValue` loop with `PDO::PARAM_INT` would be needed.
        // For now, this is simpler and usually sufficient.

        return [$sql, $bindings];
    }

    protected function hydrate(array $attributes): Model
    {
        /** @var Model $model */
        $model = new $this->modelClass();
        return $model->newFromBuilder($attributes);
    }

    protected function eagerLoadRelations(array $models): void
    {
        $this->eagerLoadLevel($models, $this->eagerLoad);
    }

    /**
     * Eagerly load the relationships on a set of models.
     * This method is recursive to handle nested relationships.
     *
     * @param  array  $models
     * @param  array  $relations
     * @return void
     */
    protected function eagerLoadLevel(array $models, array $relations): void
    {
        if (empty($models)) {
            return;
        }

        foreach ($relations as $relationName => $relationData) {
            if (empty($relationData['nested']) && !is_callable($relationData['constraints'])) {
                continue;
            }

            // Get an instance of the relation from the first model to perform logic
            /** @var \Core\ORM\Relations\Relation $relation */
            $relation = $models[0]->{$relationName}();

            // Apply user-defined constraints from the 'with' closure
            if (is_callable($relationData['constraints'])) {
                $relationData['constraints']($relation->getQuery());
            }

            // Add constraints to load all related child models
            $relation->addEagerConstraints($models);

            // Execute the query to get all child models
            $results = $relation->get();

            // Match the child models to their respective parent models
            $relation->match($models, $results, $relationName);

            // If there are nested relationships, load them recursively
            if (!empty($relationData['nested'])) {
                $relatedModels = [];
                foreach ($models as $model) {
                    $loadedRelation = $model->getAttribute($relationName);
                    if ($loadedRelation) {
                        if ($loadedRelation instanceof Model) {
                            $relatedModels[] = $loadedRelation;
                        } else {
                            $relatedModels = array_merge($relatedModels, $loadedRelation);
                        }
                    }
                }
                $this->eagerLoadLevel($relatedModels, $relationData['nested']);
            }
        }
    }

    protected function parseWithRelations(array $relations): array
    {
        $parsed = [];

        foreach ($relations as $name => $constraints) {
            // If the key is numeric, it means the value is the relation name
            // and there are no constraints. e.g., ['posts', 'tags']
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $segments = explode('.', $name);
            $container = &$parsed;

            // Traverse the segments to build the nested structure
            foreach ($segments as $i => $segment) {
                if (!isset($container[$segment])) {
                    $container[$segment] = ['nested' => [], 'constraints' => null];
                }

                // The constraints apply to the last segment
                if ($i === count($segments) - 1 && is_callable($constraints)) {
                    $container[$segment]['constraints'] = $constraints;
                }

                $container = &$container[$segment]['nested'];
            }
        }
        return $parsed;
    }

    /**
     * @deprecated Use getConnection('write') instead. This method is for backward compatibility.
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->getConnection('write');
    }

    /**
     * Get the PDO connection instance for a specific type.
     */
    protected function getConnection(string $type): PDO
    {
        return Connection::get(null, $type);
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

        $bindings = array_merge($whereBindings, $limitBindings);

        $stmt = $this->getConnection('read')->prepare($sql);
        $stmt->execute($bindings);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

            // Find and remove the soft delete where clause
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
    public function forceDelete(): bool
    {
        [$whereClause, $bindings] = $this->buildWhereClause();
        $sql = "DELETE FROM {$this->table}" . $whereClause;

        $stmt = $this->getConnection('write')->prepare($sql);
        return $stmt->execute($bindings);
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
            // Cannot restore on a model that doesn't use soft deletes.
            return false;
        }

        // To restore, we should typically query for trashed items first.
        // e.g., Post::onlyTrashed()->where('user_id', 1)->restore();
        return $this->update([$model->getDeletedAtColumn() => null]);
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

        // We can reuse the update method. It will respect existing where clauses
        // and only update the `deleted_at` timestamp.
        return $this->update([$column => $time]);
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
}
