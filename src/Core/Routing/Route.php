<?php

namespace Core\Routing;

class Route
{
    public string $method;
    public string $uri;
    public mixed $handler;
    public array $middleware = [];
    public array $parameters = [];

    /**
     * Custom model bindings for the route.
     * e.g., ['post' => 'slug']
     *
     * @var array
     */
    public array $bindings = [];

    public ?string $name = null;

    /**
     * The middleware group for the route.
     *
     * @var string|null
     */
    public ?string $group = null;

    public function __construct(string $method, string $uri, mixed $handler)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->handler = $handler;
    }

    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set the name for the route.
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = $name;
        app(Router::class)->addNamedRoute($name, $this->uri);
        return $this;
    }

    /**
     * Set the middleware group for the route.
     *
     * @param string|null $group
     * @return $this
     */
    public function group(?string $group): self
    {
        $this->group = $group;
        return $this;
    }
    public function model(string $param, string $class): self
    {
        $this->bindings[$param] = $class;
        return $this;
    }
}
