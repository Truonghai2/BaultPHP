<?php

namespace Core;

use Core\ContextualBindingBuilder;

class Application
{
    protected array $bindings = [];
    protected array $instances = [];
    protected array $aliases = [];
    protected array $contextual = [];

    /** @var string[] Stack of classes currently being resolved. */
    protected array $resolvingStack = [];

    /** @var array Cache for reflection objects to speed up instantiation. */
    protected array $reflectionCache = [];

    protected array $serviceProviders = [];
    protected array $resolvingCallbacks = [];
    protected string $basePath;

    public function __construct(string $basePath = null) {
        $this->basePath = $basePath;
    }

    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    public function bind(string $abstract, mixed $concrete, bool $singleton = false): void
    {
        $this->bindings[$abstract] = compact('concrete', 'singleton');
    }

    public function singleton(string $abstract, mixed $concrete): void
    {
        $this->bind($abstract, $concrete, true);
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
     * @throws \Exception if a circular dependency is detected.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Add the abstract to the resolving stack to detect circular dependencies.
        $this->resolvingStack[] = $abstract;

        try {
            $object = $this->build($abstract);
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
     */
    protected function build(string $concrete): mixed
    {
        if (isset($this->bindings[$concrete])) {
            $binding = $this->bindings[$concrete]['concrete'];
            if (is_callable($binding)) {
                return $binding($this);
            }
            $concrete = $binding;
        }

        $reflector = $this->getReflector($concrete);
        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete();
        }

        if (in_array($concrete, $this->resolvingStack, true) && end($this->resolvingStack) !== $concrete) {
            throw new \Exception("Circular dependency detected while resolving [$concrete].");
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
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
                throw new \Exception("Cannot resolve un-typed parameter \${$dependency->getName()} in class {$context}");
            }

            $results[] = $this->resolveClassParameter($dependency);
        }
        return $results;
    }

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
     *
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            $callback = explode('@', $callback, 2);
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;
            $instance = $this->make($class);
            $callback = [$instance, $method];
        }

        $reflector = $this->getFunctionReflector($callback);
        $dependencies = [];
        foreach ($reflector->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $type = $parameter->getType();
            $typeName = ($type && !$type->isBuiltin()) ? $type->getName() : null;
    
            if (array_key_exists($paramName, $parameters)) {
                // --- BẮT ĐẦU LOGIC ROUTE MODEL BINDING ---
                // If the parameter is type-hinted as a Model, resolve it from the database
                if ($typeName && class_exists($typeName) && is_subclass_of($typeName, \Core\ORM\Model::class)) {
                    // Assumes your Model has a static findOrFail method
                    $dependencies[] = $typeName::findOrFail($parameters[$paramName]); // This assumes an ORM like Eloquent
                    continue;
                }
                // --- KẾT THÚC LOGIC ROUTE MODEL BINDING ---
                $dependencies[] = $parameters[$paramName];
            } elseif ($typeName && class_exists($typeName)) {
                $dependencies[] = $this->make($typeName);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            }
        }
        return $callback(...$dependencies);
    }

    protected function getReflector(string $class): \ReflectionClass
    {
        if (!class_exists($class)) {
            throw new \Exception("Class [$class] not found.");
        }

        if (!isset($this->reflectionCache[$class])) {
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
        $provider = new $providerClass($this);
        $provider->register();

        $this->serviceProviders[] = $provider;
    }

    public function boot(): void
    {
        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
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
}
