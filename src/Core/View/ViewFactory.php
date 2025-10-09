<?php

namespace Core\View;

use Core\Contracts\StatefulService;
use Core\Contracts\View\Factory as FactoryContract;
use Core\Contracts\View\View as ViewContract;
use Core\FileSystem\Filesystem;

class ViewFactory implements FactoryContract, StatefulService
{
    protected array $viewPaths;
    protected Filesystem $files;
    protected Compiler $compiler;

    /**
     * Dữ liệu được chia sẻ cho tất cả các view.
     *
     * @var array
     */
    protected array $shared = [];

    /**
     * Mảng các namespace đã đăng ký.
     *
     * @var array
     */
    protected array $namespaces = [];

    /**
     * Mảng các section đã được render.
     * @var array
     */
    protected array $sections = [];

    /**
     * Stack các section đang được mở.
     * @var array
     */
    protected array $sectionStack = [];

    /**
     * Stack các component đang được render.
     * @var array
     */
    protected array $componentStack = [];

    /**
     * Stack các slot đang được mở.
     * @var array
     */
    protected array $slots = [];

    /**
     * Mảng các stack nội dung (dùng cho @push).
     * @var array
     */
    protected array $stacks = [];

    /**
     * The stack of loops.
     * @var array
     */
    protected array $loopsStack = [];

    /**
     * The name of the layout being extended.
     *
     * @var string|null
     */
    protected ?string $layout = null;

    public function __construct(Compiler $compiler, Filesystem $files, array $viewPaths)
    {
        $this->compiler = $compiler;
        $this->files = $files;
        $this->viewPaths = $viewPaths;
    }

    /**
     * Tạo một instance View mới.
     */
    public function make(string $view, array $data = [], array $mergeData = []): ViewContract
    {
        $path = $this->findView($view);

        // Hợp nhất dữ liệu theo thứ tự ưu tiên: shared -> mergeData -> data.
        // Thêm factory vào view data để có thể truy cập qua $__env
        $viewData = array_merge(['__env' => $this], $this->shared, $mergeData, $data);

        return new View($this, $view, $this->compiler, $path, $viewData);
    }

    public function exists(string $view): bool
    {
        try {
            $this->findView($view);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function share($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $k => $v) {
            $this->shared[$k] = $v;
        }

        return $value;
    }

    public function getShared(): array
    {
        return $this->shared;
    }

    public function composer($views, $callback)
    {
        // Ghi chú: Một implementation đầy đủ của view composer cần một hệ thống event.
        // Đây là một placeholder để đáp ứng contract.
        return (array) $views;
    }

    /**
     * Thêm một namespace cho view.
     *
     * @param  string  $namespace
     * @param  string  $path
     * @return void
     */
    public function addNamespace(string $namespace, string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            throw new \InvalidArgumentException("View namespace path [{$path}] is not a valid directory.");
        }
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Tìm đường dẫn đầy đủ của một file view.
     */
    public function findView(string $view): string
    {
        $extensions = ['blade.php', 'php'];

        if (str_contains($view, '::')) {
            [$namespace, $viewName] = explode('::', $view, 2);

            if (!isset($this->namespaces[$namespace])) {
                throw new \InvalidArgumentException("No view namespace found for [{$namespace}].");
            }

            $path = $this->namespaces[$namespace];
            $viewPath = str_replace('.', '/', $viewName);

            foreach ($extensions as $extension) {
                $fullPath = rtrim($path, '/\\') . '/' . $viewPath . '.' . $extension;
                if ($this->files->exists($fullPath)) {
                    return $fullPath;
                }
            }

            throw new \InvalidArgumentException("View [{$view}] not found in namespace [{$namespace}].");
        }

        // Fallback về các đường dẫn mặc định
        $viewPath = str_replace('.', '/', $view);
        foreach ($this->viewPaths as $path) {
            foreach ($extensions as $extension) {
                $fullPath = rtrim($path, '/\\') . '/' . $viewPath . '.' . $extension;
                if ($this->files->exists($fullPath)) {
                    return $fullPath;
                }
            }
        }

        throw new \InvalidArgumentException("View [{$view}] not found in default paths.");
    }

    /**
     * Bắt đầu một section mới.
     */
    public function startSection(string $section, ?string $content = null): void
    {
        if ($content === null) {
            $this->sectionStack[] = $section;
            ob_start();
        } else {
            $this->extendSection($section, $content);
        }
    }

    /**
     * Dừng section hiện tại.
     */
    public function stopSection(): string
    {
        $last = array_pop($this->sectionStack);
        $this->extendSection($last, ob_get_clean());
        return $last;
    }

    /**
     * Thêm nội dung vào một section.
     */
    protected function extendSection(string $section, string $content): void
    {
        if (isset($this->sections[$section])) {
            $content = str_replace(Compiler::PARENT_PLACEHOLDER, $content, $this->sections[$section]);
        }
        $this->sections[$section] = $content;
    }

    /**
     * Lấy nội dung của một section.
     */
    public function yieldContent(string $section, string $default = ''): string
    {
        return $this->sections[$section] ?? $default;
    }

    /**
     * Lấy nội dung của section cuối cùng đã được dừng.
     */
    public function yieldSection(): string
    {
        if (empty($this->sectionStack)) {
            return '';
        }
        return $this->yieldContent(end($this->sectionStack));
    }

    /**
     * Register a view file to be extended.
     * This is called by the compiled view code from an @extends directive.
     *
     * @param  string  $view
     * @return void
     */
    public function extend(string $view): void
    {
        $this->layout = $view;
    }

    /**
     * Get the layout that is being extended and reset it for the next render.
     *
     * @return string|null
     */
    public function popLayout(): ?string
    {
        $layout = $this->layout;
        $this->layout = null;

        return $layout;
    }

    /**
     * Dọn dẹp tất cả trạng thái tạm thời của factory.
     * Rất quan trọng cho các môi trường long-running như Swoole.
     */
    public function resetState(): void
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->stacks = [];
        $this->componentStack = [];
        $this->slots = [];
        $this->loopsStack = [];
        $this->layout = null;
    }

    /**
     * Alias for resetState() for backward compatibility or convenience.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->resetState();
    }

    /**
     * Bắt đầu render một component.
     */
    public function startComponent(string $view, array $data = []): void
    {
        ob_start();

        $componentData = [
            'attributes' => new ComponentAttributeBag($data),
        ];

        $this->componentStack[] = [
            'view' => $view,
            'data' => $componentData,
            'slots' => [],
        ];
    }

    /**
     * Render component cuối cùng trong stack.
     *
     * @return string
     * @throws \Exception
     */
    public function renderComponent(): string
    {
        $component = array_pop($this->componentStack);
        $data = $component['data'];

        $component['slots']['slot'] = ob_get_clean();

        $data = array_merge($component['data'], ['slots' => (object) $component['slots']]);

        return $this->make($component['view'], $data)->render();
    }

    /**
     * Bắt đầu một slot mới.
     */
    public function slot(string $name): void
    {
        ob_start();
        $this->slots[] = $name;
    }

    /**
     * Dừng slot hiện tại.
     */
    public function endSlot(): void
    {
        $slotName = array_pop($this->slots);
        $content = ob_get_clean();

        if (!empty($this->componentStack)) {
            $this->componentStack[count($this->componentStack) - 1]['slots'][$slotName] = $content;
        }
    }

    public function startPush(string $stack): void
    {
        ob_start();
        $this->sectionStack[] = $stack;
    }

    public function stopPush(): void
    {
        $stack = array_pop($this->sectionStack);
        $this->stacks[$stack][] = ob_get_clean();
    }

    public function yieldPushContent(string $stack): string
    {
        if (!isset($this->stacks[$stack])) {
            return '';
        }
        return implode('', $this->stacks[$stack]);
    }

    /**
     * Add a new loop to the top of the loop stack.
     *
     * @param  \Countable|array  $data
     * @return void
     */
    public function addLoop($data): void
    {
        $length = is_array($data) || $data instanceof \Countable ? count($data) : 0;
        $parent = end($this->loopsStack);

        $this->loopsStack[] = (object) [
            'iteration' => 0,
            'index' => 0,
            'remaining' => $length,
            'count' => $length,
            'first' => true,
            'last' => $length === 0,
            'depth' => count($this->loopsStack) + 1,
            'parent' => $parent ?: null,
        ];
    }

    /**
     * Increment the loop iteration.
     */
    public function incrementLoop(): void
    {
        $loop = $this->getLastLoop();

        $loop->iteration++;
        $loop->index = $loop->iteration - 1;
        $loop->remaining--;
        $loop->first = ($loop->iteration === 1);
        $loop->last = ($loop->remaining === 0);
    }

    /**
     * Pop the last loop from the loop stack.
     */
    public function popLoop(): void
    {
        array_pop($this->loopsStack);
    }

    /**
     * Get the last loop from the loop stack.
     */
    public function getLastLoop(): ?\stdClass
    {
        if ($loop = end($this->loopsStack)) {
            return $loop;
        }

        return null;
    }

    /**
     * Render nội dung của một file đã được biên dịch.
     */
    public function evaluatePath(string $path, array $data): string
    {
        $obLevel = ob_get_level();

        ob_start();

        try {
            (function ($__path, $__data) {
                extract($__data, EXTR_SKIP);
                include $__path;
            })($path, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw $e;
        }

        return ltrim(ob_get_clean());
    }
}
