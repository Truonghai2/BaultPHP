<?php

namespace Core\View\Contracts;

/**
 * Interface Factory
 *
 * Defines the contract for the view factory, which is the main entry point
 * for creating and rendering views.
 */
interface Factory
{
    /**
     * Renders a view with the given data.
     */
    public function make(string $view, array $data = []): string;

    /**
     * Adds a namespace for module views.
     */
    public function addNamespace(string $namespace, string|array $hints): void;
}
