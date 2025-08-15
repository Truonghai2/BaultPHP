<?php

namespace Core\ORM\Concerns;

use Core\ORM\Scopes\ActiveScope;

/**
 * Trait này cung cấp chức năng cho các model có trạng thái 'active'.
 * Nó tự động áp dụng ActiveScope để chỉ truy vấn các bản ghi đang hoạt động theo mặc định.
 */
trait HasActiveState
{
    /**
     * Boot the 'has active state' trait for a model.
     *
     * @return void
     */
    public static function bootHasActiveState(): void
    {
        static::addGlobalScope(new ActiveScope());
    }

    /**
     * Lấy tên cột 'is_active' đầy đủ (bao gồm tên bảng).
     *
     * @return string
     */
    public function getQualifiedIsActiveColumn(): string
    {
        return $this->getTable() . '.' . $this->getIsActiveColumn();
    }

    /**
     * Lấy tên cột 'is_active'. Có thể được ghi đè bằng hằng số trong model.
     *
     * @return string
     */
    public function getIsActiveColumn(): string
    {
        return defined('static::IS_ACTIVE_COLUMN') ? static::IS_ACTIVE_COLUMN : 'is_active';
    }
}
