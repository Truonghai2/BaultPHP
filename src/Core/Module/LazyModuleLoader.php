<?php

declare(strict_types=1);

namespace Core\Module;

use Core\Application;
use Core\ProviderRepository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Loads "on_request" modules when the current request path matches their route prefix(es).
 *
 * Used to reduce bootstrap time and memory: only modules whose routes are actually
 * hit are loaded. The mapping (path prefix → module) is cached in bootstrap/cache/lazy_modules.php.
 */
final class LazyModuleLoader
{
    private const CACHE_FILE = 'bootstrap/cache/lazy_modules.php';

    /** @var list<array{name: string, providers: list<string>, pathPrefixes: list<string>}> */
    private ?array $lazyInfos = null;

    /** @var array<string, true> Set of module names already loaded in this request/worker */
    private array $loadedModules = [];

    public function __construct(
        private readonly Application $app,
        private readonly ProviderRepository $providerRepository,
    ) {
    }

    /**
     * Ensure all modules that declare a matching path prefix for this request are loaded.
     * Call this before router->dispatch() so routes from those modules are available.
     */
    public function ensureModulesLoadedForRequest(ServerRequestInterface $request): void
    {
        $path = $request->getUri()->getPath();
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->getLazyModuleInfos() as $info) {
            $name = $info['name'];
            if (isset($this->loadedModules[$name])) {
                continue;
            }
            foreach ($info['pathPrefixes'] as $prefix) {
                if ($path === $prefix || str_starts_with($path . '/', $prefix . '/')) {
                    $this->loadModule($name, $info['providers']);
                    break;
                }
            }
        }
    }

    /**
     * Load a single module by name (register and boot all its providers).
     *
     * @param list<string> $providerClasses
     */
    public function loadModule(string $moduleName, array $providerClasses): void
    {
        if (isset($this->loadedModules[$moduleName])) {
            return;
        }

        foreach ($providerClasses as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                continue;
            }
            $this->app->registerProviderAndBoot($providerClass);
        }

        $this->loadedModules[$moduleName] = true;
    }

    /**
     * Return the list of module names that have been lazy-loaded in this process.
     *
     * @return list<string>
     */
    public function getLoadedModuleNames(): array
    {
        return array_keys($this->loadedModules);
    }

    /**
     * Get lazy module infos (from cache or repository).
     *
     * @return list<array{name: string, providers: list<string>, pathPrefixes: list<string>}>
     */
    public function getLazyModuleInfos(): array
    {
        if ($this->lazyInfos !== null) {
            return $this->lazyInfos;
        }

        $cachePath = $this->app->basePath(self::CACHE_FILE);

        if (file_exists($cachePath) && !$this->app['config']->get('app.debug', true)) {
            $this->lazyInfos = require $cachePath;
            if (is_array($this->lazyInfos)) {
                return $this->lazyInfos;
            }
        }

        $this->lazyInfos = $this->providerRepository->getLazyModuleInfos();
        return $this->lazyInfos;
    }

    /**
     * Build and write the lazy-module cache file (for use by route:cache or optimize).
     */
    public function warmCache(): void
    {
        $infos = $this->providerRepository->getLazyModuleInfos();
        $path = $this->app->basePath(self::CACHE_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $content = "<?php\n\nreturn " . var_export($infos, true) . ";\n";
        file_put_contents($path, $content);
    }
}
