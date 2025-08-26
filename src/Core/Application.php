<?php

namespace Core;

use Core\Exceptions\ContainerException;
use Core\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;

class Application implements ContainerInterface, \ArrayAccess
{
    /**
     * The BaultPHP framework version.
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    protected array $bindings = [];
    protected array $instances = [];
    protected array $aliases = [];
    protected array $contextual = [];

    /** @var array<string, array<int, \Closure>> The registered extenders for services. */
    protected array $extenders = [];

    /** @var array<string, array<int, string>> Mảng lưu trữ các tag và các abstract được gán. */
    protected array $tags = [];

    /** @var array The compiled container factories. */
    protected array $compiledFactories = [];

    /** @var static|null The shared instance of the application. */
    protected static ?self $instance = null;

    /** @var string[] Stack of classes currently being resolved. */
    protected array $resolvingStack = [];

    /** @var array Cache for reflection objects to speed up instantiation. */
    protected array $reflectionCache = [];

    /** @var bool Indicates if the application has "booted". */
    protected bool $isBooted = false;

    /** @var bool Indicates if the application has been bootstrapped. */
    protected bool $hasBeenBootstrapped = false;

    /** @var array Callbacks to run after the application is booted. */
    protected array $bootedCallbacks = [];

    /** @var array<int, \Core\Support\ServiceProvider> The array of loaded service providers. */
    protected array $serviceProviders = [];

    /** @var array<string, bool> The array of registered service providers. */
    protected array $registeredProviders = [];

    /** @var array<string, string> The mapping of deferred services to their providers. */
    protected array $deferredServices = [];
    protected array $resolvingCallbacks = [];
    protected string $basePath;

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath;
        $this->registerBaseBindings();
        $this->loadCompiledContainer();
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract]);
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * This is the `has` method required by the PSR-11 ContainerInterface.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     * This is the `get` method required by the PSR-11 ContainerInterface.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     */
    public function get(string $id)
    {
        return $this->make($id);
    }

    /**
     * Set the shared instance of the application.
     *
     * @param self $container
     * @return self
     */
    public static function setInstance(self $container): self
    {
        return static::$instance = $container;
    }

    /**
     * Get the shared instance of the application.
     *
     * @return static|null
     */
    public static function getInstance(): ?self
    {
        return static::$instance;
    }

    protected function registerBaseBindings(): void
    {
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(Application::class, $this);

        $this->singleton('config', function ($app) {
            return new \Core\Config($app);
        });
    }

    /**
     * Load the compiled container file if it exists.
     */
    protected function loadCompiledContainer(): void
    {
        $path = $this->bootstrapPath('cache/container.php');

        if (file_exists($path)) {
            $compiled = require $path;
            $this->compiledFactories = $compiled['factories'] ?? [];
            $this->aliases = array_merge($compiled['aliases'] ?? [], $this->aliases);
        }
    }

    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Alias a type to a different name.
     *
     * @param string $abstract
     * @param string $alias
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function bind(string $abstract, mixed $concrete = null, bool $singleton = false): void
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = compact('concrete', 'singleton');
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete ?? $abstract, true);
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * This allows for decorating or modifying a service after it has been resolved.
     *
     * @param  string  $abstract
     * @param  \Closure  $closure
     * @return void
     */
    public function extend(string $abstract, \Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            // If the instance is already resolved, we apply the extension immediately.
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
            return;
        }

        // Otherwise, store the extender to be applied when it's resolved.
        $this->extenders[$abstract][] = $closure;
    }

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->instances[$abstract] = $instance;
        return $instance;
    }

    /**
     * Resolve the given type from the container.
     *
     * @throws LogicException if a circular dependency is detected.
     * @throws RuntimeException if the type cannot be resolved.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->deferredServices[$abstract]) && !isset($this->instances[$abstract])) {
            $this->register($this->deferredServices[$abstract]);
        }

        if (empty($parameters) && isset($this->compiledFactories[$abstract])) {
            return $this->compiledFactories[$abstract]($this);
        }

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (in_array($abstract, $this->resolvingStack)) {
            $path = implode(' -> ', $this->resolvingStack) . " -> {$abstract}";
            throw new ContainerException("Circular dependency detected while resolving: [{$path}]");
        }

        $this->resolvingStack[] = $abstract;

        try {
            $object = $this->build($abstract, $parameters);
        } finally {
            array_pop($this->resolvingStack);
        }

        // Apply extenders to the resolved instance.
        if (isset($this->extenders[$abstract])) {
            foreach ($this->extenders[$abstract] as $extender) {
                $object = $extender($object, $this);
            }
        }

        if (isset($this->bindings[$abstract]['singleton']) && $this->bindings[$abstract]['singleton']) {
            $this->instances[$abstract] = $object;
        }

        if (isset($this->resolvingCallbacks[$abstract])) {
            foreach ($this->resolvingCallbacks[$abstract] as $callback) {
                $callback($object, $this);
            }
        }
        return $object;
    }

    /**
     * Build an instance of the given class.
     *
     * @throws RuntimeException
     */
    protected function build(string $concrete, array $parameters = []): mixed
    {
        if (isset($this->bindings[$concrete])) {
            $binding = $this->bindings[$concrete]['concrete'];
            if (is_callable($binding)) {
                return $binding($this, $parameters);
            }
            $concrete = $binding;
        }

        $reflector = $this->getReflector($concrete);
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @throws RuntimeException
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $type = $dependency->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $results[] = $this->resolveClassParameter($dependency);
                continue;
            }

            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
                continue;
            }

            $context = end($this->resolvingStack);
            $paramName = $dependency->getName();
            $message = "Cannot resolve parameter `{$paramName}`";
            if ($context) {
                $message .= " in class `{$context}`";
            }
            $message .= '. Please provide a type hint or a default value.';

            throw new ContainerException($message);
        }

        return $results;
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @throws RuntimeException
     */
    protected function resolveClassParameter(\ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType()->getName();
        $context = end($this->resolvingStack);

        if (isset($this->contextual[$context][$type])) {
            $implementation = $this->contextual[$context][$type];
            return is_callable($implementation) ? $implementation($this) : $this->make($implementation);
        }

        return $this->make($type);
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            $callback = explode('@', $callback, 2);
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;
            $instance = is_object($class) ? $class : $this->make($class);
            $callback = [$instance, $method];
        }

        $reflector = $this->getFunctionReflector($callback);
        $dependencies = [];
        foreach ($reflector->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $type = $parameter->getType();
            $typeName = ($type && !$type->isBuiltin()) ? $type->getName() : null;

            if (array_key_exists($paramName, $parameters)) {
                $dependencies[] = $parameters[$paramName];
            } elseif ($typeName && (class_exists($typeName) || interface_exists($typeName))) {
                $dependencies[] = $this->make($typeName);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new ContainerException("Unresolvable dependency resolving [$parameter] in " . $reflector->getName());
            }
        }
        return $callback(...$dependencies);
    }

    protected function getReflector(string $class): \ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new NotFoundException("Class or interface [{$class}] not found.");
            }
            $this->reflectionCache[$class] = new \ReflectionClass($class);
        }
        return $this->reflectionCache[$class];
    }

    protected function getFunctionReflector(callable $callback): \ReflectionFunctionAbstract
    {
        if (is_array($callback)) {
            $key = get_class($callback[0]) . '@' . $callback[1];
        } else {
            $key = spl_object_hash($callback);
        }

        if (!isset($this->reflectionCache[$key])) {
            $this->reflectionCache[$key] = is_array($callback)
                ? new \ReflectionMethod($callback[0], $callback[1])
                : new \ReflectionFunction($callback);
        }

        return $this->reflectionCache[$key];
    }

    public function register(string $providerClass): void
    {
        if ($this->isProviderRegistered($providerClass)) {
            return;
        }

        $provider = new $providerClass($this);

        if ($provider instanceof \Core\Contracts\Support\DeferrableProvider) {
            $this->deferProvider($provider);
        } else {
            $provider->register();
        }

        $this->registeredProviders[$providerClass] = true;

        $this->serviceProviders[] = $provider;
    }

    /**
     * Defer the registration of a service provider.
     *
     * @param \Core\Contracts\Support\DeferrableProvider $provider
     * @return void
     */
    protected function deferProvider(\Core\Contracts\Support\DeferrableProvider $provider): void
    {
        foreach ($provider->provides() as $service) {
            $this->deferredServices[$service] = get_class($provider);
        }
    }

    protected function isProviderRegistered(string $providerClass): bool
    {
        return isset($this->registeredProviders[$providerClass]);
    }

    /**
     * Bootstrap the application's service providers.
     *
     * This method discovers, registers, and boots all service providers.
     * It's designed to be called once per worker in a long-running process
     * like Swoole, to avoid re-bootstrapping on every request.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if ($this->hasBeenBootstrapped()) {
            return;
        }

        $cachedProvidersPath = $this->getCachedProvidersPath();
        if (file_exists($cachedProvidersPath) && !config('app.debug')) {
            $providers = require $cachedProvidersPath;
        } else {
            $providerRepository = new ProviderRepository($this);
            $providers = $providerRepository->getAllProviders();
        }

        foreach ($providers as $provider) {
            $this->register($provider);
        }

        $this->boot();

        $this->hasBeenBootstrapped = true;
    }

    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $this->call([$provider, 'boot']);
            }
        }

        $this->isBooted = true;

        foreach ($this->bootedCallbacks as $callback) {
            $this->call($callback);
        }
    }

    /**
     * Register a new "booted" callback.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function booted(\Closure $callback): void
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $this->call($callback);
        }
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->isBooted;
    }

    /**
     * Register a new "after resolving" callback.
     */
    public function afterResolving(string $abstract, \Closure $callback): void
    {
        $this->resolvingCallbacks[$abstract][] = $callback;
    }

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool
    {
        return isset($this->instances[$abstract]);
    }

    /**
     * Remove a resolved instance from the instance cache.
     * This is useful in long-running applications to prevent memory leaks.
     *
     * @param  string  $abstract
     * @return void
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Get the alias for an abstract if available.
     */
    public function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    public function addContextualBinding(string $concrete, string $abstract, string|\Closure $implementation): void
    {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath('bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : ''));
    }

    public function getCachedRoutesPath(): string
    {
        return $this->basePath('bootstrap/cache/routes.php');
    }

    public function getCachedProvidersPath(): string
    {
        return $this->basePath('bootstrap/cache/providers.php');
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Gán một tag cho một hoặc nhiều abstract.
     *
     * @param  string|array  $abstracts
     * @param  string  $tag
     * @return void
     */
    public function tag(string|array $abstracts, string $tag): void
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }

        foreach ((array) $abstracts as $abstract) {
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * Get the class names of all bindings tagged with a given tag.
     *
     * @param  string  $tag
     * @return array<string>
     */
    public function getTaggedClasses(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Resolve tất cả các binding đã được gán một tag nhất định.
     *
     * @param  string  $tag
     * @return array<object>
     */
    public function getTagged(string $tag): array
    {
        return isset($this->tags[$tag]) ? array_map([$this, 'make'], $this->tags[$tag]) : [];
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        return in_array(php_sapi_name(), ['cli', 'phpdbg']);
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->make('translator')->getLocale();
    }

    /**
     * Get the application's bindings for compilation.
     *
     * @return array
     */
    public function getBindingsForCompilation(): array
    {
        return $this->bindings;
    }

    /**
     * Get the application's aliases for compilation.
     *
     * @return array
     */
    public function getAliasesForCompilation(): array
    {
        return $this->aliases;
    }

    /**
     * Determine if a given offset exists.
     * Required by the ArrayAccess interface.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->bound($key);
    }

    /**
     * Get the value for a given offset.
     * Required by the ArrayAccess interface.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->make($key);
    }

    /**
     * Set the value for a given offset.
     * Required by the ArrayAccess interface.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->instance($key, $value);
    }

    /**
     * Unset the value for a given offset.
     * Required by the ArrayAccess interface.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        unset($this->bindings[$key], $this->instances[$key], $this->aliases[$key]);
    }
}
