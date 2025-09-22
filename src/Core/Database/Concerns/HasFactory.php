<?php

namespace Core\Database\Concerns;

use Core\Database\Factory;
use RuntimeException;

/**
 * Trait HasFactory
 *
 * Cung cấp phương thức `factory()` cho các model, cho phép dễ dàng
 * tạo các instance của factory tương ứng.
 *
 * @package Core\Database\Concerns
 */
trait HasFactory
{
    /**
     * Lấy factory instance cho model.
     *
     * @return \Core\Database\Factory
     */
    public static function factory(): Factory
    {
        $factory = 'Database\\Factories\\' . class_basename(static::class) . 'Factory';

        if (!class_exists($factory)) {
            throw new RuntimeException("Không thể tìm thấy factory: {$factory}");
        }

        return new $factory();
    }
}