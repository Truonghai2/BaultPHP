<?php

namespace Core\View;

use Core\Contracts\View\Factory as FactoryContract;
use Core\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;

/**
 * Class Factory
 *
 * "Nhà máy" chính để tạo và render view.
 * Class này đóng vai trò trung gian, tái sử dụng các thành phần cốt lõi
 * của illuminate/view (finder, engine resolver) nhưng trả về các đối tượng
 * View tuân thủ contract của Core framework.
 *
 * @package Core\View
 */
class Factory implements FactoryContract
{
    /**
     * The engine resolver instance.
     *
     * @var \Illuminate\View\Engines\EngineResolver
     */
    protected EngineResolver $engines;

    /**
     * The view finder instance.
     *
     * @var \Illuminate\View\FileViewFinder
     */
    protected FileViewFinder $finder;

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected Dispatcher $events;

    /**
     * The array of data shared across all views.
     *
     * @var array
     */
    protected array $shared = [];

    /**
     * The extension to engine bindings.
     *
     * @var array<string, string>
     */
    protected array $extensions = [];

    /**
     * Create a new view factory instance.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $engines
     * @param  \Illuminate\View\FileViewFinder  $finder
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     */
    public function __construct(EngineResolver $engines, FileViewFinder $finder, Dispatcher $events)
    {
        $this->engines = $engines;
        $this->finder = $finder;
        $this->events = $events;
        // Chia sẻ factory instance cho tất cả các view, hữu ích cho các layout.
        $this->share('__env', $this);
    }

    /**
     * Get a new view instance.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  array  $mergeData
     * @return \Core\Contracts\View\View
     */
    public function make(string $view, array $data = [], array $mergeData = []): ViewContract
    {
        $path = $this->finder->find($view);
        $data = array_merge($mergeData, $data);

        $viewInstance = $this->viewInstance($view, $path, $data);

        // Fire the "creating" event, which is used by view composers.
        $this->events->dispatch('creating: ' . $view, [$this, $viewInstance]);

        return $viewInstance;
    }

    /**
     * Create a new view instance from the given arguments.
     *
     * @param  string  $view
     * @param  string  $path
     * @param  array  $data
     * @return \Core\Contracts\View\View
     */
    protected function viewInstance(string $view, string $path, array $data): ViewContract
    {
        return new View(
            $this,
            $this->getEngineFromPath($path),
            $view,
            $path,
            $data,
        );
    }

    /**
     * Get the appropriate view engine for the given path.
     *
     * @param  string  $path
     * @return \Illuminate\Contracts\View\Engine
     *
     * @throws \InvalidArgumentException
     */
    protected function getEngineFromPath(string $path)
    {
        $extension = $this->getExtension($path);

        if (is_null($extension)) {
            throw new InvalidArgumentException("Unrecognized extension in file: {$path}.");
        }

        $engine = $this->extensions[$extension];

        return $this->engines->resolve($engine);
    }

    /**
     * Get the extension of a file path.
     *
     * @param  string  $path
     * @return string|null
     */
    protected function getExtension(string $path): ?string
    {
        foreach (array_keys($this->extensions) as $extension) {
            if (str_ends_with($path, '.' . $extension)) {
                return $extension;
            }
        }

        return null;
    }

    public function exists(string $view): bool
    {
        try {
            $this->finder->find($view);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function share($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            $this->shared[$key] = $value;
        }

        return $value;
    }

    public function getShared(): array
    {
        return $this->shared;
    }

    public function addExtension(string $extension, string $engine): void
    {
        $this->extensions[$extension] = $engine;
    }

    public function composer($views, $callback)
    {
        $views = (array) $views;

        foreach ($views as $view) {
            // This relies on the event naming convention used by Illuminate's view system.
            // When a view is "creating", this event will fire.
            $this->events->listen("creating: {$view}", $callback);
        }

        return $views;
    }
}
