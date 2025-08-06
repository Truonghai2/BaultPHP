<?php

namespace Core;

class ContextualBindingBuilder
{
    /**
     * The underlying container instance.
     */
    protected Application $container;

    /**
     * The concrete class being built.
     */
    protected string $concrete;

    /**
     * The abstract target of the binding.
     */
    protected string $needs;

    public function __construct(Application $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    public function needs(string $abstract): self
    {
        $this->needs = $abstract;
        return $this;
    }

    public function give(string|\Closure $implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->needs, $implementation);
    }
}
