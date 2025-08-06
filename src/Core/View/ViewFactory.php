<?php

namespace Core\View;

use Core\View\Contracts\Factory as FactoryContract;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * Class ViewFactory
 *
 * The main class for rendering views.
 */
class ViewFactory implements FactoryContract
{
    /** @var array<string, string|array> The registered view namespaces. */
    protected array $namespaces = [];

    /** @var string|null The layout to be extended. */
    protected ?string $layout = null;


    public function __construct(
        private Engine $engine,
        private Filesystem $files,
        private array $viewPaths
    ) {
    }

    /**
     * Create and render a view.
     */
    public function make(string $view, array $data = []): string
    {
        $path = $this->findView($view);

        return $this->engine->get($path, $data, $this);
    }

    /**
     * Add a new namespace to the loader.
     */
    public function addNamespace(string $namespace, string|array $hints): void
    {
        $this->namespaces[$namespace] = $hints;
    }

    /**
     * Find the fully qualified path to a view.
     */
    protected function findView(string $name): string
    {
        // Handle namespace syntax like 'module::view.name'
        if (str_contains($name, '::')) {
            [$namespace, $view] = explode('::', $name, 2);

            if (!isset($this->namespaces[$namespace])) {
                throw new InvalidArgumentException("View namespace [{$namespace}] not found.");
            }

            $viewPath = str_replace('.', '/', $view) . '.php';
            $namespacePaths = (array) $this->namespaces[$namespace];

            foreach ($namespacePaths as $path) {
                $fullPath = rtrim($path, '/\\') . '/' . $viewPath;
                if ($this->files->exists($fullPath)) {
                    return $fullPath;
                }
            }
        } else {
            // Handle standard views
            $viewPath = str_replace('.', '/', $name) . '.php';

            foreach ($this->viewPaths as $path) {
                $fullPath = $path . '/' . $viewPath;
                if ($this->files->exists($fullPath)) {
                    return $fullPath;
                }
            }
        }

        throw new InvalidArgumentException("View [{$name}] not found.");
    }
}
