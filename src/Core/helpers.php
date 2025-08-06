<?php

use Http\ResponseFactory;
use Symfony\Component\VarDumper\VarDumper;

function service(string $contract): object
{
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

if (!function_exists('log_message')) {
    function log_message(string $level, string $message, array $context = [])
    {
        $logFile = storage_path('logs/app.log');
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level]: $message " . (empty($context) ? '' : json_encode($context)) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
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

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler on the default queue.
     *
     * @param  object  $job
     * @return void
     */
    function dispatch(object $job): void
    {
        /** @var \Core\Queue\QueueManager $queue */
        $queue = app(\Core\Queue\QueueManager::class);
        $queue->connection()->push($job);
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
    function resource_path(string $path = ''): string
    {
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

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view, or the view factory.
     *
     * @param  string|null  $view
     * @param  array   $data
     * @param  array   $mergeData
     * @return \Core\View\Contracts\Factory|string
     */
    function view(?string $view = null, array $data = [], array $mergeData = [])
    {
        /** @var \Core\View\Contracts\Factory $factory */
        $factory = app('view');

        if (is_null($view)) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

use Psr\Http\Message\ResponseInterface;

if (!function_exists('response')) {
    /**
     * Tạo một response instance hoặc lấy ra response factory.
     *
     * @param  string|null  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \Core\Http\ResponseFactory|\Psr\Http\Message\ResponseInterface
     */
    function response(string $content = null, int $status = 200, array $headers = []): ResponseFactory|ResponseInterface
    {
        /** @var ResponseFactory $factory */
        $factory = app(ResponseFactory::class);

        if (is_null($content)) {
            return $factory;
        }

        return $factory->make($content, $status, $headers);
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param  string|null  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @return string|array|null
     */
    function __(?string $key = null, array $replace = [], ?string $locale = null)
    {
        return app('translator')->get($key, $replace, $locale);
    }
}


if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }
        
}


if (!function_exists('vite')) {
    function vite(string|array $entrypoints): string
    {
        $hotFile = public_path('hot');
        $manifestPath = public_path('build/manifest.json');
        $output = '';

        if (file_exists($hotFile)) {
            // Môi trường Development (HMR)
            $host = file_get_contents($hotFile);
            $output .= "<script type=\"module\" src=\"{$host}/@vite/client\"></script>";
            foreach ((array) $entrypoints as $entry) {
                $output .= "<script type=\"module\" src=\"{$host}/{$entry}\"></script>";
            }
        } elseif (file_exists($manifestPath)) {
            // Môi trường Production
            $manifest = json_decode(file_get_contents($manifestPath), true);
            foreach ((array) $entrypoints as $entry) {
                if (isset($manifest[$entry])) {
                    $output .= "<script type=\"module\" src=\"/build/{$manifest[$entry]['file']}\"></script>";
                    if (!empty($manifest[$entry]['css'])) {
                        foreach ($manifest[$entry]['css'] as $css) {
                            $output .= "<link rel=\"stylesheet\" href=\"/build/{$css}\">";
                        }
                    }
                }
            }
        }
        return $output;
    }
}

use Laminas\Escaper\Escaper;

if (!function_exists('esc')) {
    /**
     * Escape a string for a specific context to prevent XSS attacks.
     *
     * This function uses laminas-escaper to provide contextual escaping.
     *
     * @param  string|null  $value The value to escape.
     * @param  string $context The escaping context ('html', 'js', 'css', 'url', 'attr'). Defaults to 'html'.
     * @return string The escaped string.
     */
    function esc(?string $value, string $context = 'html'): string
    {
        if ($value === null) {
            return '';
        }

        static $escaper;
        if (!$escaper) {
            // Using 'utf-8' is crucial for security and proper character handling.
            $escaper = new Escaper('utf-8');
        }

        return match ($context) {
            'js' => $escaper->escapeJs($value),
            'css' => $escaper->escapeCss($value),
            'url' => $escaper->escapeUrl($value),
            'attr' => $escaper->escapeHtmlAttr($value),
            default => $escaper->escapeHtml($value),
        };
    }
}
