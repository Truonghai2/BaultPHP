<?php

/**
 * Helper functions cho ExampleModule
 * File này được autoload qua composer.json
 */

if (!function_exists('example_module_log')) {
    /**
     * Quick logging helper cho module.
     */
    function example_module_log(string $message, array $context = []): void
    {
        \Core\Support\Facades\Log::info("[ExampleModule] {$message}", $context);
    }
}

if (!function_exists('example_module_config')) {
    /**
     * Get config value cho module.
     */
    function example_module_config(string $key, mixed $default = null): mixed
    {
        return config("example-module.{$key}", $default);
    }
}

