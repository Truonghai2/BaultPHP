<?php

namespace Core\Support;

use Symfony\Component\VarDumper\VarDumper;

/**
 * Dumper class của Core.
 * Đóng gói logic dump để dễ dàng thay đổi implementation.
 * Ưu tiên sử dụng Symfony VarDumper nếu có, nếu không sẽ fallback về var_dump.
 */
class Dumper
{
    /**
     * Dump a variable.
     *
     * @param  mixed  ...$vars
     * @return void
     */
    public function dump(...$vars): void
    {
        foreach ($vars as $var) {
            if (class_exists(VarDumper::class)) {
                VarDumper::dump($var);
            } else {
                var_dump($var);
            }
        }
    }
}
