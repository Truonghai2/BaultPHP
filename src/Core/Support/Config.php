<?php

namespace Core\Support;

class Config
{
    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file = $parts[0];
        $path = array_slice($parts, 1);

        $config = require base_path("config/$file.php");

        foreach ($path as $segment) {
            if (!isset($config[$segment])) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }
}
