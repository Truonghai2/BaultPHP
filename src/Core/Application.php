<?php

namespace Core;

use RuntimeException;
use LogicException;

class Application
{
    /**
     * The BaultFrame framework version.
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    protected array $bindings = [];
    protected array $instances = [];
    protected array $aliases = [];
    protected array $contextual = [];

    /** @var static|null The shared instance of the application. */
    protected static ?self $instance = null;

    /** @var string[] Stack of classes currently being resolved. */
    protected array $resolvingStack = [];

    /** @var array Cache for reflection objects to speed up instantiation. */
    protected array $reflectionCache = [];

    /** @var bool Indicates if the application has "booted". */
    protected bool $isBooted = false;

    protected array $serviceProviders = [];
    protected array $resolvingCallbacks = [];
    protected string $basePath;

    public function __construct(string $basePath = null) {
        $this->basePath = $basePath;
        $this->registerBaseBindings();
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

    public function bind(string $abstract, mixed $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = compact('concrete', 'singleton');
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        // If no concrete implementation is provided, we'll assume the abstract is the concrete.
        $this->bind($abstract, $concrete ?? $abstract, true);
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

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Detect circular dependencies.
        if (in_array($abstract, $this->resolvingStack)) {
            $path = implode(' -> ', $this->resolvingStack) . " -> {$abstract}";
            throw new LogicException("Circular dependency detected while resolving: [{$path}]");
        }

        // Add the abstract to the resolving stack to detect circular dependencies.
        $this->resolvingStack[] = $abstract;

        try {
            $object = $this->build($abstract, $parameters);
        } finally {
            // Remove the abstract from the stack once resolution is complete.
            array_pop($this->resolvingStack);
        }

        // If it's a singleton, store the instance for future requests.
        if (isset($this->bindings[$abstract]['singleton']) && $this->bindings[$abstract]['singleton']) {
            $this->instances[$abstract] = $object;
        }

        // Fire "after resolving" callbacks.
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
            throw new RuntimeException("Class [$concrete] is not instantiable.");
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
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($dependency->isDefaultValueAvailable()) {
                    $results[] = $dependency->getDefaultValue();
                    continue;
                }
                $context = end($this->resolvingStack);
                throw new RuntimeException("Cannot resolve un-typed parameter \${$dependency->getName()} in class {$context}");
            }

            $results[] = $this->resolveClassParameter($dependency);
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

        // Check for a contextual binding.
        if (isset($this->contextual[$context][$type])) {
            $implementation = $this->contextual[$context][$type];
            return is_callable($implementation) ? $implementation($this) : $this->make($implementation);
        }

        // Fallback to normal resolution.
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
            // Nếu phần tử đầu tiên đã là một đối tượng, hãy sử dụng nó.
            // Ngược lại, resolve nó từ container.
            $instance = is_object($class) ? $class : $this->make($class);
            $callback = [$instance, $method];
        }

        $reflector = $this->getFunctionReflector($callback);
        $dependencies = [];
        foreach ($reflector->getParameters() as $parameter) {
            $paramName = $parameter->getName(); // This line is correct
            $type = $parameter->getType();
            $typeName = ($type && !$type->isBuiltin()) ? $type->getName() : null;

            if (array_key_exists($paramName, $parameters)) {
                $dependencies[] = $parameters[$paramName];
            } elseif ($typeName && (class_exists($typeName) || interface_exists($typeName))) {
                $dependencies[] = $this->make($typeName);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException("Unresolvable dependency resolving [$parameter] in " . $reflector->getName());
            }
        }
        return $callback(...$dependencies);
    }

    protected function getReflector(string $class): \ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            if (!class_exists($class)) {
                throw new RuntimeException("Class [$class] not found.");
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
            // Using spl_object_hash for closures is a decent way to cache them for a single request.
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
        error_log("    --> [Application] Registering provider: " . $providerClass);
        $provider = new $providerClass($this);
        $provider->register();

        $this->serviceProviders[] = $provider;
        error_log("    --> [Application] Provider registered: " . $providerClass);
    }

    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                error_log("    --> [Application] Booting provider: " . get_class($provider));
                $this->call([$provider, 'boot']);
                error_log("    --> [Application] Provider booted: " . get_class($provider));
            }
        }

        $this->isBooted = true;
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

    public function getCachedRoutesPath(): string
    {
        return $this->basePath('bootstrap/cache/routes.php');
    }

    public function getCachedProvidersPath(): string
    {
        return $this->basePath('bootstrap/cache/providers.php');
    }

    public function loadCachedProviders(array $providers): void
    {
        foreach ($providers as $providerClass) {
            if (class_exists($providerClass)) {
                $this->register($providerClass);
            }
        }
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
}
