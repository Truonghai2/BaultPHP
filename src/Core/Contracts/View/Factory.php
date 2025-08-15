<?php

namespace Core\Contracts\View;

/**
 * Interface Factory
 *
 * Định nghĩa contract cho một "nhà máy" view.
 * Bất kỳ class nào muốn hoạt động như một view factory trong framework
 * đều phải implement interface này. Điều này giúp tách biệt hoàn toàn
 * logic ứng dụng khỏi implementation cụ thể của view engine.
 *
 * @package Core\Contracts\View
 */
interface Factory
{
    /**
     * Kiểm tra xem một view có tồn tại không.
     *
     * @param  string  $view
     * @return bool
     */
    public function exists(string $view): bool;

    /**
     * Lấy một instance của đối tượng View.
     *
     * @param  string  $view
     * @param  array   $data
     * @param  array   $mergeData
     * @return \Core\Contracts\View\View
     */
    public function make(string $view, array $data = [], array $mergeData = []): View;

    /**
     * Chia sẻ một mẩu dữ liệu cho tất cả các view.
     *
     * @param  string|array  $key
     * @param  mixed   $value
     * @return mixed
     */
    public function share($key, $value = null);

    /**
     * Lấy ra mảng dữ liệu đã được chia sẻ.
     *
     * @return array
     */
    public function getShared(): array;

    /**
     * Register a view composer event.
     *
     * @param  array|string  $views
     * @param  \Closure|string  $callback
     * @return array
     */
    public function composer($views, $callback);
}
