<?php

use Core\Application;
use Core\Debug\SwooleDumpException;
use Http\ResponseFactory;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

function service(string $contract): object
{
    return app(\Core\Contracts\ServiceRegistry::class)->get($contract);
}

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $abstract
     * @param  array   $parameters
     * @return mixed|\Core\Application
     */
    function app(string $abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Application::getInstance();
        }

        return Application::getInstance()->make($abstract, $parameters);
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
    /**
    * Dumps the given variables and ends the execution of the current request gracefully.
    *
    * @param  mixed  ...$vars
    * @return void
    *
    * @throws \Core\Debug\SwooleDumpException
    */
    function dd(...$vars): void
    {
        $dumper = new HtmlDumper();
        $cloner = new VarCloner();

        $output = fopen('php://memory', 'r+b');
        $dumper->dump($cloner->cloneVar($vars), $output);

        throw new SwooleDumpException(stream_get_contents($output, -1, 0));
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

use Core\Facades\Translate;
use Core\Support\Vite;

if (!function_exists('__')) {

    function __(?string $key = null, array $replace = [], ?string $locale = null)
    {
        if (is_null($key)) {
            return null;
        }

        return Translate::get($key, $replace, $locale);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

}

if (!function_exists('vite')) {
    /**
     * Get the Vite assets for the application.
     *
     * @param string|string[] $entrypoints
     * @return \Core\Support\HtmlString
     */
    function vite(string|array $entrypoints): \Core\Support\HtmlString
    {
        if (!app()->bound(Vite::class)) {
            app()->singleton(Vite::class);
        }

        $htmlContent = app(Vite::class)($entrypoints);

        return new \Core\Support\HtmlString((string) $htmlContent);
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
        static $coreEscaper;
        if (!$coreEscaper) {
            $coreEscaper = new \Core\Support\Escaper();
        }

        return $coreEscaper->escape($value, $context);
    }
}

use Core\Contracts\View\Factory as ViewFactoryContract;
use Core\Routing\UrlGenerator;

if (! function_exists('view')) {
    /**
     * Lấy một instance của view factory hoặc tạo một view.
     *
     * Hàm này cung cấp một lối tắt tiện lợi để truy cập vào hệ thống view của framework.
     * - Gọi `view()` không có tham số sẽ trả về chính View Factory.
     * - Gọi `view('welcome', ['data'])` sẽ tạo và trả về một đối tượng View.
     *
     * @param  string|null  $view Tên của view (ví dụ: 'welcome' hoặc 'pages.about').
     * @param  array  $data Dữ liệu để truyền cho view.
     * @return \Core\Contracts\View\Factory|\Core\Contracts\View\View
     */
    function view(string $view = null, array $data = [])
    {
        $factory = app(ViewFactoryContract::class);

        if (is_null($view)) {
            return $factory;
        }

        return $factory->make($view, $data);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a URL for an asset.
     *
     * @param  string  $path
     * @return string
     */
    function asset(string $path): string
    {
        $baseUrl = rtrim(config('app.url', '/'), '/');

        $path = ltrim($path, '/');

        return "{$baseUrl}/{$path}";
    }
}

if (!function_exists('route')) {
    /**
     * Generate the URL for a given named route.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @return string
     */
    function route(string $name, array $parameters = []): string
    {
        return app(\Core\Routing\Router::class)->url($name, $parameters);
    }
}

if (!function_exists('url')) {
    /**
     * Generate a fully qualified URL to the given path.
     *
     * @param  string  $path
     * @return string
     */
    function url(string $path): string
    {
        return UrlGenerator::to($path);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve an old input value from the session flash data.
     * This is useful for repopulating forms after a validation error.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function old(string $key, $default = null)
    {
        if (!app()->has('session')) {
            return $default;
        }

        /** @var \Symfony\Component\HttpFoundation\Session\SessionInterface $session */
        $session = app('session');

        $oldInput = $session->getFlashBag()->get('_old_input')[0] ?? [];

        return $oldInput[$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value.
     *
     * This function retrieves the current CSRF token from the session. The token
     * is managed by the session store and is used to protect against CSRF attacks.
     *
     * @return string The CSRF token.
     * @throws \RuntimeException if the session is not available.
     */
    function csrf_token(): string
    {
        return app(\Core\Security\CsrfManager::class)->getTokenValue('_token');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token form field.
     *
     * @return \Core\Support\HtmlString
     */
    function csrf_field(): \Core\Support\HtmlString
    {
        return new \Core\Support\HtmlString('<input type="hidden" name="_token" value="' . csrf_token() . '" autocomplete="off">');
    }
}

if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector or create a redirect response.
     *
     * - `redirect()`: Returns the Redirector instance.
     * - `redirect('/home')`: Creates a RedirectResponse to '/home'.
     * - `redirect()->back()`: Creates a RedirectResponse to the previous URL.
     *
     * @param  string|null  $to
     * @param  int     $status
     * @param  array   $headers
     * @return \Core\Http\Redirector|\Core\Http\RedirectResponse
     */
    function redirect(string $to = null, int $status = 302, array $headers = [])
    {
        $redirector = app(\Core\Http\Redirector::class);

        return $to ? $redirector->to($to, $status, $headers) : $redirector;
    }
}

if (!function_exists('cookie')) {
    /**
     * Create/queue a cookie or retrieve the cookie manager.
     *
     * This helper provides a convenient interface to the CookieManager.
     * - cookie(): returns the CookieManager instance.
     * - cookie('name'): gets the value of the 'name' cookie from the current request.
     * - cookie('name', 'value', 10): queues a cookie to be sent with the response.
     *
     * @param  string|null  $name
     * @param  string|null  $value
     * @param  int  $minutes
     * @param  string|null  $path
     * @param  string|null  $domain
     * @param  bool|null  $secure
     * @param  bool  $httpOnly
     * @param  bool  $raw
     * @param  string|null  $sameSite
     * @return \Core\Cookie\CookieManager|mixed|void
     */
    function cookie(
        ?string $name = null,
        ?string $value = null,
        int $minutes = 0,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = null,
    ) {
        /** @var \Core\Cookie\CookieManager $cookieManager */
        $cookieManager = app(\Core\Cookie\CookieManager::class);

        if (is_null($name)) {
            return $cookieManager;
        }

        if (is_null($value)) {
            return $cookieManager->get($name);
        }

        $cookieManager->queue($name, $value, $minutes, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }
}

use Core\Exceptions\DumpException;

if (!function_exists('sdd')) {
    /**
     * Dumps the passed variables and ends the script for the current request
     * in a way that is safe for a Swoole environment.
     *
     * "Swoole Dump and Die"
     *
     * @param  mixed  ...$vars
     * @return void
     * @throws DumpException
     */
    function sdd(...$vars): void
    {
        $responseFactory = new ResponseFactory();

        ob_start();

        $cloner = new VarCloner();
        $dumper = new HtmlDumper();

        // Custom styling for better readability on dark backgrounds
        $dumper->setStyles([
            'default' => 'background-color:#18171B; color:#FF8400; line-height:1.2em; font:14px Menlo, Monaco, Consolas, monospace; word-wrap: break-word; white-space: pre-wrap; position:relative; z-index:99999; word-break: break-all',
            'num' => 'font-weight:bold; color:#1299DA',
            'const' => 'font-weight:bold',
            'str' => 'font-weight:bold; color:#56DB3A',
            'note' => 'color:#1299DA',
            'ref' => 'color:#A0A0A0',
            'public' => 'color:#FFFFFF',
            'protected' => 'color:#FFFFFF',
            'private' => 'color:#FFFFFF',
            'meta' => 'color:#B729D9',
            'key' => 'color:#56DB3A',
            'index' => 'color:#1299DA',
        ]);

        foreach ($vars as $var) {
            $dumper->dump($cloner->cloneVar($var));
        }

        $output = ob_get_clean();

        $response = $responseFactory->make($output, 200);

        throw new DumpException($response);
    }
}
