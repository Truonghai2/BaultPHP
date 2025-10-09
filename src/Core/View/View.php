<?php

namespace Core\View;

use Core\Contracts\View\View as ViewContract;

class View implements ViewContract
{
    public function __construct(
        protected ViewFactory $factory,
        protected string $view,
        protected Compiler $compiler,
        protected string $path,
        protected array $data,
    ) {
    }

    /**
     * Render view thành một chuỗi HTML.
     */
    public function render(): string
    {
        try {
            if ($this->compiler->isExpired($this->path)) {
                $this->compiler->compile($this->path);
            }

            $compiledPath = $this->compiler->getCompiledPath($this->path);

            $this->factory->evaluatePath($compiledPath, $this->data);

            if ($layout = $this->factory->popLayout()) {
                $layoutPath = $this->factory->findView(trim($layout, "'\""));

                if ($this->compiler->isExpired($layoutPath)) {
                    $this->compiler->compile($layoutPath);
                }

                $compiledPath = $this->compiler->getCompiledPath($layoutPath);
            }

            return $this->factory->evaluatePath($compiledPath, $this->data);
        } finally {
            $this->factory->resetState();
        }
    }

    /**
     * Tự động gọi render khi đối tượng được ép kiểu thành chuỗi.
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Lấy tên của view.
     */
    public function name(): string
    {
        return $this->view;
    }

    /**
     * Lấy mảng dữ liệu của view.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Thêm một mẩu dữ liệu vào view.
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }
}
