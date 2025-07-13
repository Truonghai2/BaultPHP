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


if (!function_exists('class_uses_recursive')) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param  object|string  $class
     * @return array
     */
    function class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        // We use array_reverse to ensure parent traits are included before child traits.
        // This is important for trait method overriding.
        foreach (array_reverse(class_parents($class)) + [$class => $class] as $c) {
            $results += trait_uses_recursive($c);
        }

        return array_unique($results);
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * Returns all traits used by a trait.
     *
     * @param  string  $trait
     * @return array
     */
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $t) {
            $traits += trait_uses_recursive($t);
        }

        return $traits;
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true': return true;
            case 'false': return false;
            case 'null': return null;
            case 'empty': return '';
        }

        return $value;
    }
}

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @return string
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}