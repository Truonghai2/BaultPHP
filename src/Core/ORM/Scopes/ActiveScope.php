<?php

namespace Core\ORM\Scopes;

use Core\ORM\Model;
use Core\ORM\QueryBuilder;

class ActiveScope implements Scope
{
    /**
     * Áp dụng scope vào một QueryBuilder cho trước.
     *
     * @param  \Core\ORM\QueryBuilder  $builder
     * @param  \Core\ORM\Model  $model
     * @return void
     */
    public function apply(QueryBuilder $builder, Model $model)
    {
        // Scope này giờ đây dựa vào contract được cung cấp bởi model.
        // Nó sẽ gọi phương thức getQualifiedIsActiveColumn() trên model.
        // Nếu model không implement phương thức này (ví dụ: không dùng trait HasActiveState),
        // một lỗi sẽ xảy ra, điều này là tốt vì nó chỉ ra một lỗi lập trình rõ ràng.
        $builder->where($model->getQualifiedIsActiveColumn(), 1);
    }
}
