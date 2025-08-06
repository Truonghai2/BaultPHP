<?php

namespace Core\Contracts\Cache;

interface Store
{
    /**
     * Lấy một item từ cache bằng key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key);

    /**
     * Lưu một item vào cache trong một số giây nhất định.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds);

    /**
     * Xóa một item khỏi cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key);

    /**
     * Xóa tất cả các item khỏi cache.
     *
     * @return bool
     */
    public function flush();
}
