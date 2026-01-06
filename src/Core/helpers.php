<?php

use App\Http\ResponseFactory;
use Core\Application;
use Core\Debug\SwooleDumpException;
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

if (!function_exists('cache')) {
    /**
     * Get the cache manager instance or a cache value
     *
     * @param string|array|null $key Cache key or array of key-value pairs to set
     * @param mixed $default Default value if key doesn't exist
     * @return mixed|\Core\Cache\CacheManager
     */
    function cache(string|array|null $key = null, mixed $default = null): mixed
    {
        $cache = app(\Core\Cache\CacheManager::class);

        // If no key provided, return cache manager instance
        if ($key === null) {
            return $cache;
        }

        // If array provided, set multiple values
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $cache->put($k, $v);
            }
            return null;
        }

        // Get single value
        return $cache->get($key, $default);
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
use Psr\Http\Message\ServerRequestInterface;

if (!function_exists('request')) {
    /**
     * Get the current request instance.
     *
     * @return \Core\Http\Request
     */
    function request(): \Core\Http\Request
    {
        $psr7Request = app(ServerRequestInterface::class);
        return new \Core\Http\Request($psr7Request);
    }
}

if (!function_exists('response')) {
    /**
     * Tạo một response instance hoặc lấy ra response factory.
     *
     * @param  string|null  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \App\Http\ResponseFactory|\Psr\Http\Message\ResponseInterface
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
        return app(UrlGenerator::class)->route($name, $parameters);
    }
}

if (!function_exists('route_exists')) {
    /**
     * Check if a named route exists.
     *
     * @param  string  $name
     * @return bool
     */
    function route_exists(string $name): bool
    {
        try {
            app(UrlGenerator::class)->route($name);
            return true;
        } catch (\Throwable) {
            return false;
        }
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

        /** @var Core\Contracts\Session\SessionInterface $session */
        $session = app('session');

        $oldInput = $session->getFlashBag()->get('_old_input') ?? [];

        return $oldInput[$key] ?? $default;
    }
}

if (!function_exists('session')) {
    /**
     * Get / set the session instance or session value.
     *
     * - `session()`: returns the session manager instance.
     * - `session('key')`: gets the value of 'key' from the session.
     * - `session(['key' => 'value'])`: sets 'key' to 'value' in the session.
     * @param  string|array|null  $key
     * @param  mixed  $default
     * @return \Core\Contracts\Session\SessionInterface|mixed|null
     */
    function session($key = null, $default = null)
    {
        if (!app()->has('session')) {
            throw new RuntimeException('Session store is not available. Make sure the session middleware is enabled.');
        }

        /** @var \Core\Session\SessionManager $session */
        $session = app('session');

        if (is_null($key)) {
            return $session;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $session->set($k, $v);
            }
            return null;
        }

        return $session->get($key, $default);
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

if (!function_exists('isDockerEnvironment')) {
    /**
     * Check if the application is running in a Docker environment.
     *
     * @return bool
     */
    function isDockerEnvironment(): bool
    {
        // Check for Docker-specific environment variables
        if (getenv('DOCKER_CONTAINER') || getenv('CONTAINER')) {
            return true;
        }

        // Check for Docker-specific files
        if (file_exists('/.dockerenv') || file_exists('/proc/1/cgroup') && str_contains(file_get_contents('/proc/1/cgroup'), 'docker')) {
            return true;
        }

        // Check for Docker in process name
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $processUser = posix_getpwuid(posix_geteuid());
            if (isset($processUser['name']) && str_contains($processUser['name'], 'docker')) {
                return true;
            }
        }

        return false;
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
    function cookie(...$args)
    {
        /** @var \Core\Cookie\CookieManager $cookieManager */
        $cookieManager = app(\Core\Cookie\CookieManager::class);

        if (empty($args)) {
            return $cookieManager;
        }

        $name = $args[0];
        $value = $args[1] ?? null;

        if (is_null($value)) {
            return $cookieManager->get($name, $args[2] ?? null);
        }

        if (is_array($args[2] ?? null)) {
            $options = $args[2];
            $minutes = $options['minutes'] ?? 0;
            $path = $options['path'] ?? null;
            $domain = $options['domain'] ?? null;
            $secure = $options['secure'] ?? null;
            $httpOnly = $options['httpOnly'] ?? true;
            $raw = $options['raw'] ?? false;
            $sameSite = $options['sameSite'] ?? null;
        } else {
            [$minutes, $path, $domain, $secure, $httpOnly, $raw, $sameSite] = array_slice($args, 2) + [0, null, null, null, true, false, null];
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

use Core\Module\ModuleSettingsManager;

if (!function_exists('module_setting')) {
    /**
     * Get a module setting value.
     *
     * @param string $moduleName
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function module_setting(string $moduleName, string $key, mixed $default = null): mixed
    {
        return app(ModuleSettingsManager::class)->get($moduleName, $key, $default);
    }
}

if (!function_exists('set_module_setting')) {
    /**
     * Set a module setting value.
     *
     * @param string $moduleName
     * @param string $key
     * @param mixed $value
     * @param array $options
     * @return bool
     */
    function set_module_setting(string $moduleName, string $key, mixed $value, array $options = []): bool
    {
        return app(ModuleSettingsManager::class)->set($moduleName, $key, $value, $options);
    }
}

if (!function_exists('module_settings')) {
    /**
     * Get all settings for a module.
     *
     * @param string $moduleName
     * @param string|null $group
     * @return array
     */
    function module_settings(string $moduleName, ?string $group = null): array
    {
        return app(ModuleSettingsManager::class)->getAll($moduleName, $group);
    }
}

if (!function_exists('has_module_setting')) {
    /**
     * Check if a module setting exists.
     *
     * @param string $moduleName
     * @param string $key
     * @return bool
     */
    function has_module_setting(string $moduleName, string $key): bool
    {
        return app(ModuleSettingsManager::class)->has($moduleName, $key);
    }
}

if (!function_exists('delete_module_setting')) {
    /**
     * Delete a module setting.
     *
     * @param string $moduleName
     * @param string $key
     * @return bool
     */
    function delete_module_setting(string $moduleName, string $key): bool
    {
        return app(ModuleSettingsManager::class)->delete($moduleName, $key);
    }
}

if (!function_exists('clear_module_settings_cache')) {
    /**
     * Clear module settings cache.
     *
     * @param string $moduleName
     * @param string|null $key
     * @return void
     */
    function clear_module_settings_cache(string $moduleName, ?string $key = null): void
    {
        app(ModuleSettingsManager::class)->clearCache($moduleName, $key);
    }
}

// ============================================================
// Block System Helpers
// ============================================================

use Modules\Cms\Domain\Repositories\BlockInstanceRepositoryInterface;
use Modules\Cms\Domain\Services\BlockRenderer;
use Modules\Cms\Domain\ValueObjects\BlockId;

if (!function_exists('render_block_region')) {
    /**
     * Render all blocks in a region
     *
     * Supports both approaches:
     * 1. render_block_region('header') - Block tự fetch data
     * 2. render_block_region('header', ['user' => $user]) - Pass data from controller
     *
     * @param string $regionName The region name (e.g., 'header', 'sidebar', 'footer')
     * @param array|null $context Additional context data from controller/view (optional)
     * @param string $contextType Context type: 'global', 'page', or 'user' (default: 'global')
     * @param int|null $contextId Context ID (page ID, user ID, etc.)
     * @param array|null $userRoles User roles for visibility check
     * @return string Rendered HTML
     */
    function render_block_region(
        string $regionName,
        ?array $context = null,
        string $contextType = 'global',
        ?int $contextId = null,
        ?array $userRoles = null,
    ): string {
        try {
            /** @var BlockRenderer $renderer */
            $renderer = app(BlockRenderer::class);

            // Get user roles if authenticated and not provided
            if ($userRoles === null && auth()->check()) {
                $userRoles = auth()->user()->getRoles() ?? [];
            }

            return $renderer->renderRegion($regionName, $contextType, $contextId, $userRoles, $context);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return sprintf(
                    '<!-- Block Region Error (%s): %s -->',
                    htmlspecialchars($regionName),
                    htmlspecialchars($e->getMessage()),
                );
            }
            return '';
        }
    }
}

if (!function_exists('render_page_blocks')) {
    /**
     * Render blocks for a specific page
     *
     * @param \Modules\Cms\Infrastructure\Models\Page|int $page Page model or ID
     * @param string $region Region name (hero, content, sidebar)
     * @param array|null $context Additional context data
     * @param array|null $userRoles User roles for visibility check
     * @return string Rendered HTML
     */
    function render_page_blocks($page, string $region = 'content', ?array $context = null, ?array $userRoles = null): string
    {
        if (is_int($page)) {
            $page = \Modules\Cms\Infrastructure\Models\Page::find($page);
        }

        if (!$page) {
            if (config('app.debug')) {
                return '<!-- No page found for render_page_blocks -->';
            }
            return '';
        }

        /** @var \Modules\Cms\Domain\Services\PageBlockRenderer $renderer */
        $renderer = app(\Modules\Cms\Domain\Services\PageBlockRenderer::class);

        // DEBUG: Disable cache and add debug info
        if (config('app.debug')) {
            $renderer->withoutCache();
        }

        try {
            $html = $renderer->renderPageBlocks($page, $region, $context, $userRoles);

            // DEBUG: Add info about blocks
            if (config('app.debug') && empty($html)) {
                $blocks = $page->blocksInRegion($region);
                $blockCount = $blocks->count();
                $blockInfo = [];
                foreach ($blocks as $block) {
                    $blockInfo[] = sprintf(
                        'Block #%d: %s (visible: %s, type: %s)',
                        $block->id,
                        $block->blockType ? $block->blockType->name : 'NULL',
                        $block->visible ? 'yes' : 'no',
                        $block->blockType ? 'exists' : 'MISSING',
                    );
                }
                return sprintf(
                    "<!-- DEBUG: Page #%d, Region '%s', Found %d blocks but rendered empty\n%s\n-->",
                    $page->id,
                    $region,
                    $blockCount,
                    implode("\n", $blockInfo),
                );
            }

            return $html;
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return sprintf(
                    '<!-- render_page_blocks ERROR: %s in %s:%d -->',
                    htmlspecialchars($e->getMessage()),
                    basename($e->getFile()),
                    $e->getLine(),
                );
            }
            logger()->error('render_page_blocks failed', [
                'page_id' => $page->id,
                'region' => $region,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}

if (!function_exists('render_block')) {
    /**
     * Render a specific block by ID
     *
     * @param int $blockId Block instance ID
     * @param array|null $context Additional context data (optional)
     * @param array|null $userRoles User roles for visibility check
     * @return string Rendered HTML
     */
    function render_block(int $blockId, ?array $context = null, ?array $userRoles = null): string
    {
        try {
            /** @var BlockRenderer $renderer */
            $renderer = app(BlockRenderer::class);

            /** @var BlockInstanceRepositoryInterface $repository */
            $repository = app(BlockInstanceRepositoryInterface::class);

            $block = $repository->findById(new BlockId($blockId));

            if (!$block) {
                if (config('app.debug')) {
                    return sprintf('<!-- Block #%d not found -->', $blockId);
                }
                return '';
            }

            // Get user roles if authenticated and not provided
            if ($userRoles === null && auth()->check()) {
                $userRoles = auth()->user()->getRoles() ?? [];
            }

            return $renderer->renderBlock($block, $userRoles, $context);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return sprintf(
                    '<!-- Block Error (#%d): %s -->',
                    $blockId,
                    htmlspecialchars($e->getMessage()),
                );
            }
            return '';
        }
    }
}

if (!function_exists('has_blocks_in_region')) {
    /**
     * Check if a region has visible blocks
     *
     * @param string $regionName Region name
     * @param string $contextType Context type (default: 'global')
     * @param int|null $contextId Context ID
     * @return bool True if region has visible blocks
     */
    function has_blocks_in_region(
        string $regionName,
        string $contextType = 'global',
        ?int $contextId = null,
    ): bool {
        try {
            $html = render_block_region($regionName, null, $contextType, $contextId);
            return !empty(trim(strip_tags($html)));
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('get_all_regions')) {
    /**
     * Get all rendered regions with their HTML content
     *
     * @param array|null $context Additional context data (optional)
     * @param string $contextType Context type (default: 'global')
     * @param int|null $contextId Context ID
     * @param array|null $userRoles User roles
     * @return array Array of region_name => html
     */
    function get_all_regions(
        ?array $context = null,
        string $contextType = 'global',
        ?int $contextId = null,
        ?array $userRoles = null,
    ): array {
        try {
            /** @var BlockRenderer $renderer */
            $renderer = app(BlockRenderer::class);

            if ($userRoles === null && auth()->check()) {
                $userRoles = auth()->user()->getRoles() ?? [];
            }

            return $renderer->renderAllRegions($contextType, $contextId, $userRoles, $context);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('render_block_with_data')) {
    /**
     * Shorthand helper to render a region with controller data
     *
     * Example:
     * Controller: return view('about', ['team' => $teamData]);
     * View: {!! render_block_with_data('team-section', ['team' => $team]) !!}
     *
     * @param string $regionName Region name
     * @param array $data Data to pass to blocks
     * @return string Rendered HTML
     */
    function render_block_with_data(string $regionName, array $data): string
    {
        return render_block_region($regionName, $data);
    }
}

use Modules\Cms\Domain\Services\BlockSyncService;

if (!function_exists('sync_blocks')) {
    /**
     * Manually trigger block sync
     *
     * @param bool $force Force sync even if recently synced
     * @return array Sync statistics
     */
    function sync_blocks(bool $force = false): array
    {
        /** @var BlockSyncService $service */
        $service = app(BlockSyncService::class);
        return $service->syncBlocks($force);
    }
}

if (!function_exists('blocks_synced')) {
    /**
     * Check if blocks are synced
     *
     * @return bool
     */
    function blocks_synced(): bool
    {
        /** @var BlockSyncService $service */
        $service = app(BlockSyncService::class);
        return $service->isSynced();
    }
}

if (!function_exists('last_block_sync')) {
    /**
     * Get last block sync time
     *
     * @return int|null Unix timestamp or null if never synced
     */
    function last_block_sync(): ?int
    {
        /** @var BlockSyncService $service */
        $service = app(BlockSyncService::class);
        return $service->getLastSyncTime();
    }
}

if (!function_exists('clear_block_sync_cache')) {
    /**
     * Clear block sync cache to force next sync
     *
     * @return void
     */
    function clear_block_sync_cache(): void
    {
        /** @var BlockSyncService $service */
        $service = app(BlockSyncService::class);
        $service->clearSyncCache();
    }
}

if (!function_exists('auto_sync_blocks_on_boot')) {
    /**
     * Auto-sync blocks on application boot (development only)
     * Call this in your service provider's boot method
     *
     * @return void
     */
    function auto_sync_blocks_on_boot(): void
    {
        if (config('app.env') === 'local' && config('cms.auto_sync_blocks', true)) {
            try {
                /** @var BlockSyncService $service */
                $service = app(BlockSyncService::class);
                $service->syncBlocks();
            } catch (\Throwable $e) {
                // Silent fail - log error but don't break boot
                if (function_exists('logger')) {
                    logger()->error('Auto-sync blocks on boot failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}

// ============================================================================
// Block Duplication & Sync Helpers
// ============================================================================

if (!function_exists('duplicate_block_to_pages')) {
    /**
     * Duplicate a block to multiple pages
     *
     * @param int $blockId Source block ID
     * @param array<int> $targetPageIds Target page IDs
     * @param bool $keepOriginalRegion Keep same region type
     * @return array Statistics
     */
    function duplicate_block_to_pages(int $blockId, array $targetPageIds, bool $keepOriginalRegion = true): array
    {
        /** @var \Modules\Cms\Domain\Services\BlockDuplicationService $service */
        $service = app(\Modules\Cms\Domain\Services\BlockDuplicationService::class);

        $block = \Modules\Cms\Infrastructure\Models\BlockInstance::find($blockId);

        if (!$block) {
            return [
                'success' => 0,
                'failed' => count($targetPageIds),
                'errors' => ['Block not found'],
            ];
        }

        return $service->duplicateToPages($block, $targetPageIds, $keepOriginalRegion);
    }
}

if (!function_exists('convert_block_to_global')) {
    /**
     * Convert a page-specific block to a global block (shown on all pages)
     *
     * @param int $blockId Block to convert
     * @param string $targetRegion Global region name ('header', 'content', 'sidebar', etc.)
     * @return bool Success status
     */
    function convert_block_to_global(int $blockId, string $targetRegion = 'content'): bool
    {
        /** @var \Modules\Cms\Domain\Services\BlockDuplicationService $service */
        $service = app(\Modules\Cms\Domain\Services\BlockDuplicationService::class);

        $block = \Modules\Cms\Infrastructure\Models\BlockInstance::find($blockId);

        if (!$block) {
            return false;
        }

        return $service->convertToGlobal($block, $targetRegion);
    }
}

if (!function_exists('duplicate_all_page_blocks')) {
    /**
     * Duplicate all blocks from one page to another
     *
     * @param int $sourcePageId Source page ID
     * @param int $targetPageId Target page ID
     * @param bool $includeHidden Include hidden blocks
     * @return array Statistics
     */
    function duplicate_all_page_blocks(int $sourcePageId, int $targetPageId, bool $includeHidden = false): array
    {
        /** @var \Modules\Cms\Domain\Services\BlockDuplicationService $service */
        $service = app(\Modules\Cms\Domain\Services\BlockDuplicationService::class);

        return $service->duplicateAllBlocksToPage($sourcePageId, $targetPageId, $includeHidden);
    }
}

if (!function_exists('sync_block_type_config')) {
    /**
     * Sync block type configuration across all pages
     * Updates all blocks of the same type with new config
     *
     * @param int $blockTypeId Block type ID
     * @param array $config New configuration
     * @return int Number of blocks updated
     */
    function sync_block_type_config(int $blockTypeId, array $config): int
    {
        /** @var \Modules\Cms\Domain\Services\BlockDuplicationService $service */
        $service = app(\Modules\Cms\Domain\Services\BlockDuplicationService::class);

        return $service->syncBlockTypeAcrossPages($blockTypeId, $config);
    }
}

// ============================================================================
// Page Blocks Helpers (Simplified Architecture)
// ============================================================================

if (!function_exists('add_page_block')) {
    /**
     * Add a block to a page
     *
     * @param int|\Modules\Cms\Infrastructure\Models\Page $page Page ID or instance
     * @param string|int|\Modules\Cms\Infrastructure\Models\BlockType $blockType Block type name, ID, or instance
     * @param string $region Region name (default: 'content')
     * @param array $options Additional options (visible, sort_order, etc.)
     * @return \Modules\Cms\Infrastructure\Models\PageBlock
     */
    function add_page_block($page, $blockType, string $region = 'content', array $options = []): \Modules\Cms\Infrastructure\Models\PageBlock
    {
        $Page = \Modules\Cms\Infrastructure\Models\Page::class;
        $BlockType = \Modules\Cms\Infrastructure\Models\BlockType::class;
        $PageBlock = \Modules\Cms\Infrastructure\Models\PageBlock::class;

        // Resolve page
        if (!$page instanceof $Page) {
            $page = $Page::find($page);
            if (!$page) {
                throw new \InvalidArgumentException('Page not found');
            }
        }

        // Resolve block type
        if (is_string($blockType)) {
            $blockType = $BlockType::where('name', $blockType)->firstOrFail();
        } elseif (is_numeric($blockType)) {
            $blockType = $BlockType::findOrFail($blockType);
        }

        if (!$blockType instanceof $BlockType) {
            throw new \InvalidArgumentException('Invalid block type');
        }

        // Get max sort_order in region
        $sortOrder = $options['sort_order'] ?? ($page->blocks()->where('region', $region)->max('sort_order') ?? 0) + 1;

        // Create page block (title and config come from block_type)
        $pageBlock = new $PageBlock([
            'page_id' => $page->id,
            'block_type_id' => $blockType->id,
            'region' => $region,
            'content' => $options['content'] ?? null,
            'sort_order' => $sortOrder,
            'visible' => $options['visible'] ?? true,
            'visibility_rules' => $options['visibility_rules'] ?? null,
            'allowed_roles' => $options['allowed_roles'] ?? null,
            'created_by' => $options['created_by'] ?? null,
        ]);

        $pageBlock->save();

        return $pageBlock;
    }
}

if (!function_exists('remove_page_block')) {
    /**
     * Remove a block from a page
     *
     * @param int|\Modules\Cms\Infrastructure\Models\PageBlock $blockId Block ID or instance
     * @return bool
     */
    function remove_page_block($blockId): bool
    {
        $PageBlock = \Modules\Cms\Infrastructure\Models\PageBlock::class;

        if ($blockId instanceof $PageBlock) {
            return $blockId->delete();
        }

        $block = $PageBlock::find($blockId);
        return $block ? $block->delete() : false;
    }
}

if (!function_exists('get_page_blocks')) {
    /**
     * Get all blocks for a page
     *
     * @param int|\Modules\Cms\Infrastructure\Models\Page $page Page ID or instance
     * @param string|null $region Optional region filter
     * @param bool $visibleOnly Only visible blocks (default: true)
     * @return \Core\Support\Collection
     */
    function get_page_blocks($page, ?string $region = null, bool $visibleOnly = true): \Core\Support\Collection
    {
        $Page = \Modules\Cms\Infrastructure\Models\Page::class;

        if (!$page instanceof $Page) {
            $page = $Page::find($page);
            if (!$page) {
                return collect([]);
            }
        }

        $query = $page->blocks();

        if ($region !== null) {
            $query->where('region', $region);
        }

        if ($visibleOnly) {
            $query->where('visible', true);
        }

        return $query->orderBy('sort_order')->get();
    }
}

if (!function_exists('duplicate_page_blocks')) {
    /**
     * Duplicate all blocks from one page to another
     *
     * @param int|\Modules\Cms\Infrastructure\Models\Page $sourcePage Source page ID or instance
     * @param int|\Modules\Cms\Infrastructure\Models\Page $targetPage Target page ID or instance
     * @return int Number of blocks duplicated
     */
    function duplicate_page_blocks($sourcePage, $targetPage): int
    {
        $Page = \Modules\Cms\Infrastructure\Models\Page::class;

        if (!$sourcePage instanceof $Page) {
            $sourcePage = $Page::find($sourcePage);
            if (!$sourcePage) {
                throw new \InvalidArgumentException('Source page not found');
            }
        }

        if (!$targetPage instanceof $Page) {
            $targetPage = $Page::find($targetPage);
            if (!$targetPage) {
                throw new \InvalidArgumentException('Target page not found');
            }
        }

        return $sourcePage->duplicateBlocksTo($targetPage);
    }
}

if (!function_exists('reorder_page_block')) {
    /**
     * Reorder a page block
     *
     * @param int|\Modules\Cms\Infrastructure\Models\PageBlock $block Block ID or instance
     * @param int $newOrder New order value
     * @return bool
     */
    function reorder_page_block($block, int $newOrder): bool
    {
        $PageBlock = \Modules\Cms\Infrastructure\Models\PageBlock::class;

        if (!$block instanceof $PageBlock) {
            $block = $PageBlock::find($block);
            if (!$block) {
                return false;
            }
        }

        $block->sort_order = $newOrder;
        return $block->save();
    }
}

if (!function_exists('move_page_block_to_region')) {
    /**
     * Move a page block to a different region
     *
     * @param int|\Modules\Cms\Infrastructure\Models\PageBlock $block Block ID or instance
     * @param string $newRegion New region name
     * @return bool
     */
    function move_page_block_to_region($block, string $newRegion): bool
    {
        $PageBlock = \Modules\Cms\Infrastructure\Models\PageBlock::class;

        if (!$block instanceof $PageBlock) {
            $block = $PageBlock::find($block);
            if (!$block) {
                return false;
            }
        }

        // Get max sort_order in target region
        $maxOrder = $PageBlock::where('page_id', $block->page_id)
            ->where('region', $newRegion)
            ->max('sort_order') ?? 0;

        $block->region = $newRegion;
        $block->sort_order = $maxOrder + 1;

        return $block->save();
    }
}

if (!function_exists('toggle_page_block_visibility')) {
    /**
     * Toggle visibility of a page block
     *
     * @param int|\Modules\Cms\Infrastructure\Models\PageBlock $block Block ID or instance
     * @return bool New visibility state
     */
    function toggle_page_block_visibility($block): bool
    {
        $PageBlock = \Modules\Cms\Infrastructure\Models\PageBlock::class;

        if (!$block instanceof $PageBlock) {
            $block = $PageBlock::find($block);
            if (!$block) {
                return false;
            }
        }

        $block->toggleVisibility();
        return $block->visible;
    }
}

if (!function_exists('get_page_block_types')) {
    /**
     * Get all available block types
     *
     * @param bool $activeOnly Only active block types (default: true)
     * @param string|null $category Filter by category
     * @return \Core\Support\Collection
     */
    function get_page_block_types(bool $activeOnly = true, ?string $category = null): \Core\Support\Collection
    {
        $BlockType = \Modules\Cms\Infrastructure\Models\BlockType::class;
        $query = $BlockType::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->orderBy('title')->get();
    }
}

if (!function_exists('get_block_type_by_name')) {
    /**
     * Get a block type by name
     *
     * @param string $name Block type name
     * @return \Modules\Cms\Infrastructure\Models\BlockType|null
     */
    function get_block_type_by_name(string $name): ?\Modules\Cms\Infrastructure\Models\BlockType
    {
        return \Modules\Cms\Infrastructure\Models\BlockType::where('name', $name)->first();
    }
}

if (!function_exists('page_has_blocks')) {
    /**
     * Check if a page has any blocks
     *
     * @param int|\Modules\Cms\Infrastructure\Models\Page $page Page ID or instance
     * @param string|null $region Optional region filter
     * @return bool
     */
    function page_has_blocks($page, ?string $region = null): bool
    {
        $Page = \Modules\Cms\Infrastructure\Models\Page::class;

        if (!$page instanceof $Page) {
            $page = $Page::find($page);
            if (!$page) {
                return false;
            }
        }

        if ($region !== null) {
            return $page->blocksInRegion($region)->isNotEmpty();
        }

        return $page->hasBlocks();
    }
}

if (!function_exists('get_page_regions')) {
    /**
     * Get all regions used by a page
     *
     * @param int|\Modules\Cms\Infrastructure\Models\Page $page Page ID or instance
     * @return array Array of region names
     */
    function get_page_regions($page): array
    {
        $Page = \Modules\Cms\Infrastructure\Models\Page::class;

        if (!$page instanceof $Page) {
            $page = $Page::find($page);
            if (!$page) {
                return [];
            }
        }

        return $page->getRegions();
    }
}

if (!function_exists('clear_page_blocks')) {
    /**
     * Remove all blocks from a page
     *
     * @param int|\Modules\Cms\Infrastructure\Models\Page $page Page ID or instance
     * @param string|null $region Optional region filter
     * @return int Number of blocks deleted
     */
    function clear_page_blocks($page, ?string $region = null): int
    {
        $Page = \Modules\Cms\Infrastructure\Models\Page::class;

        if (!$page instanceof $Page) {
            $page = $Page::find($page);
            if (!$page) {
                return 0;
            }
        }

        $query = $page->blocks();

        if ($region !== null) {
            $query->where('region', $region);
        }

        return $query->delete();
    }
}

if (!function_exists('bulk_update_page_blocks_order')) {
    /**
     * Update order of multiple blocks at once
     *
     * @param array $blockOrders Array of ['block_id' => new_order]
     * @return int Number of blocks updated
     */
    function bulk_update_page_blocks_order(array $blockOrders): int
    {
        $PageBlock = \Modules\Cms\Infrastructure\Models\PageBlock::class;
        $updated = 0;

        foreach ($blockOrders as $blockId => $order) {
            $block = $PageBlock::find($blockId);
            if ($block) {
                $block->sort_order = $order;
                if ($block->save()) {
                    $updated++;
                }
            }
        }

        return $updated;
    }
}

// ============================================================================
// APCu Helper Functions
// ============================================================================

if (!function_exists('apcu_available')) {
    /**
     * Check if APCu extension is available and enabled.
     *
     * @return bool
     */
    function apcu_available(): bool
    {
        return extension_loaded('apcu')
            && function_exists('apcu_fetch')
            && function_exists('apcu_store')
            && function_exists('apcu_delete')
            && ini_get('apc.enabled') !== '0';
    }
}

if (!function_exists('apcu_get')) {
    /**
     * Safely fetch a value from APCu cache.
     * Returns null if APCu is not available or key doesn't exist.
     *
     * @param string $key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    function apcu_get(string $key, mixed $default = null): mixed
    {
        if (!apcu_available()) {
            return $default;
        }

        $success = false;
        /** @phpstan-ignore-next-line */
        $value = \apcu_fetch($key, $success);

        return $success ? $value : $default;
    }
}

if (!function_exists('apcu_set')) {
    /**
     * Safely store a value in APCu cache.
     * Returns false if APCu is not available.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds (default: 0 = unlimited)
     * @return bool
     */
    function apcu_set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!apcu_available()) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        return \apcu_store($key, $value, $ttl);
    }
}

if (!function_exists('apcu_delete')) {
    /**
     * Safely delete a value from APCu cache.
     * Returns false if APCu is not available.
     *
     * @param string|string[] $key
     * @return bool|array
     */
    function apcu_delete(string|array $key): bool|array
    {
        if (!apcu_available()) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        return \apcu_delete($key);
    }
}

if (!function_exists('apcu_clear')) {
    /**
     * Clear all APCu cache entries.
     * Returns false if APCu is not available.
     *
     * @return bool
     */
    function apcu_clear(): bool
    {
        if (!apcu_available()) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        return \apcu_clear_cache();
    }
}

// ============================================================================
// OAuth Cache Helper Functions
// ============================================================================

if (!function_exists('oauth_cache_client')) {
    /**
     * Cache an OAuth client entity.
     * Uses multi-level caching: APCu (L2) and persistent cache (L3).
     *
     * @param string $clientId
     * @param mixed $clientEntity
     * @param int $apcuTtl APCu TTL in seconds (default: 300 = 5 minutes)
     * @param int $persistentTtl Persistent cache TTL in seconds (default: 600 = 10 minutes)
     * @return void
     */
    function oauth_cache_client(string $clientId, mixed $clientEntity, int $apcuTtl = 300, int $persistentTtl = 600): void
    {
        $key = "oauth:client:{$clientId}";

        // L2: APCu cache
        if (apcu_available()) {
            apcu_set($key, $clientEntity, $apcuTtl);
        }

        // L3: Persistent cache
        cache()->set($key, $clientEntity, $persistentTtl);
    }
}

if (!function_exists('oauth_get_client')) {
    /**
     * Get an OAuth client entity from cache.
     * Checks APCu (L2) first, then persistent cache (L3).
     *
     * @param string $clientId
     * @return mixed|null
     */
    function oauth_get_client(string $clientId): mixed
    {
        $key = "oauth:client:{$clientId}";

        // L2: Check APCu cache
        if (apcu_available()) {
            $cached = apcu_get($key);
            if ($cached !== null) {
                return $cached;
            }
        }

        // L3: Check persistent cache
        return cache()->get($key);
    }
}

if (!function_exists('oauth_clear_client_cache')) {
    /**
     * Clear OAuth client cache for a specific client.
     *
     * @param string $clientId
     * @return void
     */
    function oauth_clear_client_cache(string $clientId): void
    {
        $key = "oauth:client:{$clientId}";

        // Clear APCu
        if (apcu_available()) {
            apcu_delete($key);
        }

        // Clear persistent cache
        cache()->delete($key);
    }
}

if (!function_exists('oauth_cache_scope')) {
    /**
     * Cache an OAuth scope entity.
     * Uses multi-level caching: APCu (L2) and persistent cache (L3).
     *
     * @param string $scopeId
     * @param mixed $scopeEntity
     * @param int $apcuTtl APCu TTL in seconds (default: 3600 = 1 hour)
     * @param int $persistentTtl Persistent cache TTL in seconds (default: 3600 = 1 hour)
     * @return void
     */
    function oauth_cache_scope(string $scopeId, mixed $scopeEntity, int $apcuTtl = 3600, int $persistentTtl = 3600): void
    {
        $key = "oauth:scope:{$scopeId}";

        // L2: APCu cache
        if (apcu_available()) {
            apcu_set($key, $scopeEntity, $apcuTtl);
        }

        // L3: Persistent cache
        cache()->set($key, $scopeEntity, $persistentTtl);
    }
}

if (!function_exists('oauth_get_scope')) {
    /**
     * Get an OAuth scope entity from cache.
     * Checks APCu (L2) first, then persistent cache (L3).
     *
     * @param string $scopeId
     * @return mixed|null
     */
    function oauth_get_scope(string $scopeId): mixed
    {
        $key = "oauth:scope:{$scopeId}";

        // L2: Check APCu cache
        if (apcu_available()) {
            $cached = apcu_get($key);
            if ($cached !== null) {
                return $cached;
            }
        }

        // L3: Check persistent cache
        return cache()->get($key);
    }
}

if (!function_exists('oauth_clear_scope_cache')) {
    /**
     * Clear OAuth scope cache for a specific scope.
     *
     * @param string $scopeId
     * @return void
     */
    function oauth_clear_scope_cache(string $scopeId): void
    {
        $key = "oauth:scope:{$scopeId}";

        // Clear APCu
        if (apcu_available()) {
            apcu_delete($key);
        }

        // Clear persistent cache
        cache()->delete($key);
    }
}
