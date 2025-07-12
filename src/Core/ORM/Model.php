<?php

namespace Core\ORM;

use PDO;
use DateTime;
use Core\ORM\QueryBuilder;
use Core\ORM\Connection;

/**
 * Base Model class for ORM functionality.
 *
 * This class provides basic CRUD operations and query building capabilities.
 * It supports soft deletes, timestamps, and type casting for model attributes.
 */
class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static array $casts = [];
    protected static bool $timestamps = true;
    protected static bool $softDelete = false;

    public ?int $id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    public static function find(int $id): ?static
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function where(string $field, mixed $value): QueryBuilder
    {
        return static::query()->where($field, $value);
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    public function save(): void
    {
        $pdo = Connection::get();
        $props = get_object_vars($this);

        if (static::$timestamps) {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $this->updated_at = $now;
            if (!$this->id) $this->created_at = $now;
        }

        $columns = array_filter($props, fn($v) => $v !== null && $v !== '');

        if ($this->id) {
            $updates = implode(', ', array_map(fn($f) => "$f = :$f", array_keys($columns)));
            $stmt = $pdo->prepare("UPDATE " . static::$table . " SET $updates WHERE " . static::$primaryKey . " = :id");
        } else {
            unset($columns['id']);
            $fields = implode(', ', array_keys($columns));
            $placeholders = implode(', ', array_map(fn($f) => ":$f", array_keys($columns)));
            $stmt = $pdo->prepare("INSERT INTO " . static::$table . " ($fields) VALUES ($placeholders)");
        }

        $stmt->execute($columns);

        if (!$this->id) {
            $this->id = (int) $pdo->lastInsertId();
        }
    }

    public function delete(): void
    {
        $pdo = Connection::get();

        if (static::$softDelete) {
            $this->deleted_at = (new DateTime())->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE " . static::$table . " SET deleted_at = ? WHERE id = ?");
            $stmt->execute([$this->deleted_at, $this->id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM " . static::$table . " WHERE id = ?");
            $stmt->execute([$this->id]);
        }
    }

    public static function hydrate(array $data): static
    {
        $model = new static();
        foreach ($data as $key => $value) {
            if (isset(static::$casts[$key]) && static::$casts[$key] === 'datetime') {
                $model->$key = new DateTime($value);
            } else {
                $model->$key = $value;
            }
        }
        return $model;
    }

    public static function getTable(): string
    {
        return static::$table;
    }

    public static function getSoftDelete(): bool
    {
        return static::$softDelete ?? false;
    }
}
