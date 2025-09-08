<?php

namespace Core\Support;

class Config
{
    /**
     * @var array<string, mixed>
     */
    protected static array $loaded = [];

    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $file = $parts[0];
        $path = array_slice($parts, 1);

        if (!isset(static::$loaded[$file])) {
            $filePath = base_path("config/{$file}.php");
            static::$loaded[$file] = file_exists($filePath) ? require $filePath : [];
        }

        $config = static::$loaded[$file];

        foreach ($path as $segment) {
            if (!is_array($config) || !isset($config[$segment])) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }
}
