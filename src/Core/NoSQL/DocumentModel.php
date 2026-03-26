<?php

namespace Core\NoSQL;

use Core\Support\Facades\DB;

abstract class DocumentModel
{
    protected string $collection;
    protected string $connection = 'mongodb';
    protected array $attributes = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function query(): MongoQueryBuilder
    {
        $instance = new static();
        $connection = DB::connection($instance->connection);
        return new MongoQueryBuilder($connection->collection($instance->collection));
    }

    public static function find(string $id): ?static
    {
        $data = static::query()->where('_id', new \MongoDB\BSON\ObjectId($id))->first();
        return $data ? new static((array)$data) : null;
    }

    public function save(): bool
    {
        $builder = static::query();
        
        if (isset($this->attributes['_id'])) {
            $id = $this->attributes['_id'];
            unset($this->attributes['_id']);
            $builder->where('_id', $id)->update($this->attributes);
            $this->attributes['_id'] = $id;
        } else {
            $id = $builder->insert($this->attributes);
            $this->attributes['_id'] = new \MongoDB\BSON\ObjectId($id);
        }

        $this->exists = true;
        return true;
    }

    public function delete(): bool
    {
        if (!isset($this->attributes['_id'])) {
            return false;
        }

        static::query()->where('_id', $this->attributes['_id'])->delete();
        $this->exists = false;
        return true;
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
