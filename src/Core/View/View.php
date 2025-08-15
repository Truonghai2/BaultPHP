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
            // Biên dịch file view hiện tại (view con)
            if ($this->compiler->isExpired($this->path)) {
                $this->compiler->compile($this->path);
            }

            // Lấy đường dẫn file đã biên dịch của view con
            $compiledPath = $this->compiler->getCompiledPath($this->path);

            // Thực thi file đã biên dịch của view con để thu thập các section
            $this->factory->evaluatePath($compiledPath, $this->data);

            // Kiểm tra xem view con có kế thừa từ layout nào không
            if ($layout = $this->factory->popLayout()) {
                // Nếu có, tìm và biên dịch layout cha
                $layoutPath = $this->factory->findView(trim($layout, "'\""));
                if ($this->compiler->isExpired($layoutPath)) {
                    $this->compiler->compile($layoutPath);
                }
                $compiledPath = $this->compiler->getCompiledPath($layoutPath);
            }

            // Thực thi file đã biên dịch cuối cùng (layout cha hoặc chính nó)
            return $this->factory->evaluatePath($compiledPath, $this->data);
        } finally {
            // Luôn dọn dẹp tất cả trạng thái (sections, stacks, components) sau khi render xong.
            $this->factory->flushState();
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
