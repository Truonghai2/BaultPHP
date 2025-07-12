<?php

namespace Core\Routing;

class Route
{
    public string $method;
    public string $uri;
    public mixed $handler;
    public array $middleware = [];
    public array $parameters = [];

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
}