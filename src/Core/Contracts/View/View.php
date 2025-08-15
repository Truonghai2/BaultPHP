<?php

namespace Core\Contracts\View;

use Stringable;

/**
 * Interface View
 *
 * Định nghĩa contract cho một đối tượng View có thể được render.
 * Việc này giúp tách biệt code ứng dụng khỏi implementation cụ thể của view engine (ví dụ: Illuminate\View).
 *
 * @package Core\Contracts\View
 */
interface View extends Stringable
{
    /**
     * Lấy tên của view.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Lấy mảng dữ liệu của view.
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Thêm một mẩu dữ liệu vào view.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with($key, $value = null);
}
