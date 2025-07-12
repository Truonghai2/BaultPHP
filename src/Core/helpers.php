<?php

use Core\EventDispatcher;
use Symfony\Component\VarDumper\VarDumper;

function service(string $contract): object {
    return app(\Core\Contracts\ServiceRegistry::class)->get($contract);
}

if (!function_exists('app')) {
    function app(string $key = null)
    {
        $instance = \Core\Application::getInstance();

        if (is_null($instance)) {
            throw new \RuntimeException('Application instance has not been set. Please bootstrap the application first.');
        }

        if (is_null($key)) {
            return $instance;
        }

        // Use the 'make' method to resolve from the container
        return $instance->make($key);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}


if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $dir = base_path('app');
        return $path ? $dir . DIRECTORY_SEPARATOR . $path : $dir;
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        $dir = base_path('config');
        return $path ? $dir . DIRECTORY_SEPARATOR . $path : $dir;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $dir = base_path('storage');
        return $path ? $dir . DIRECTORY_SEPARATOR . $path : $dir;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null): mixed
    {
        /** @var \Core\Config $config */
        $config = app('config');

        if (is_null($key)) {
            return $config;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $config->set($k, $v);
            }
            return null;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('auth')) {
    function auth(): \Core\Auth\AuthManager
    {
        return app(\Core\Auth\AuthManager::class);
    }
}

if (!function_exists('config_module')) {
    function config_module(array $items): void
    {
        foreach ($items as $key => $value) {
            $_ENV['config'][$key] = $value;
        }
    }
}
if (!function_exists('event')) {
    /**
     * Dispatch an event.
     *
     * @param object|string $event
     * @param array $payload
     */
    function event(object $event): void
    {
        /** @var \Core\Events\Dispatcher $dispatcher */
        $dispatcher = app('events');
        $dispatcher->dispatch($event);
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string {
        return base_path('resources' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('dd')) {
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            if (class_exists(VarDumper::class)) {
                VarDumper::dump($var);
            } else {
                var_dump($var);
            }
        }
        die(1);
    }
}