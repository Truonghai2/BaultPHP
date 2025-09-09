<?php

namespace Core\Contracts\Filesystem;

interface Factory
{
    /**
     * Lấy một instance của filesystem disk.
     *
     * @param  string|null  $name
     * @return \Core\Contracts\Filesystem\Filesystem
     */
    public function disk($name = null);
}
