<?php

namespace Core\NoSQL;

class MongoQueryBuilder
{
    protected \MongoDB\Collection $collection;
    protected array $filters = [];
    protected array $options = [];

    public function __construct(\MongoDB\Collection $collection)
    {
        $this->collection = $collection;
    }

    public function where(string|array $filter, $operator = null, $value = null): self
    {
        if (is_array($filter)) {
            $this->filters = array_merge($this->filters, $filter);
            return $this;
        }

        if (func_num_args() === 2) {
            $this->filters[$filter] = $operator;
        } else {
            $this->filters[$filter] = $this->translateOperator($operator, $value);
        }

        return $this;
    }

    protected function translateOperator(string $operator, $value): array
    {
        return match ($operator) {
            '=' => $value,
            '>' => ['$gt' => $value],
            '>=' => ['$gte' => $value],
            '<' => ['$lt' => $value],
            '<=' => ['$lte' => $value],
            '!=' => ['$ne' => $value],
            'in' => ['$in' => (array)$value],
            'not in' => ['$nin' => (array)$value],
            'like' => ['$regex' => $value, '$options' => 'i'],
            default => $value,
        };
    }

    public function get(): array
    {
        return $this->collection->find($this->filters, $this->options)->toArray();
    }

    public function first(): ?array
    {
        return $this->collection->findOne($this->filters, $this->options);
    }

    public function insert(array $data): string
    {
        $result = $this->collection->insertOne($data);
        return (string)$result->getInsertedId();
    }

    public function update(array $data): int
    {
        $result = $this->collection->updateMany($this->filters, ['$set' => $data]);
        return $result->getModifiedCount();
    }

    public function delete(): int
    {
        $result = $this->collection->deleteMany($this->filters);
        return $result->getDeletedCount();
    }

    public function count(): int
    {
        return $this->collection->countDocuments($this->filters);
    }

    public function limit(int $value): self
    {
        $this->options['limit'] = $value;
        return $this;
    }

    public function skip(int $value): self
    {
        $this->options['skip'] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->options['sort'][$column] = strtolower($direction) === 'asc' ? 1 : -1;
        return $this;
    }
}
