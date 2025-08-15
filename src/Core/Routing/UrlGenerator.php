<?php

namespace Core\Routing;

/**
 * A simple URL generator for the framework.
 * It's responsible for creating fully qualified URLs from relative paths.
 */
class UrlGenerator
{
    /**
     * Generate a full URL to the given path.
     *
     * This method uses the 'app.url' configuration value as the base.
     *
     * @param  string  $path The relative path.
     * @return string The fully qualified URL.
     */
    public static function to(string $path): string
    {
        // Get the base URL from the configuration. Default to '/'.
        $baseUrl = rtrim(config('app.url', '/'), '/');

        // Remove leading slashes from the path to prevent issues with joining.
        $path = ltrim($path, '/');

        return "{$baseUrl}/{$path}";
    }
}
