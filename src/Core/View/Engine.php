<?php

namespace Core\View;

use Throwable;

/**
 * Class Engine.
 *
 * Quản lý việc render các file view.
 * Đây là implementation engine của riêng framework, không còn phụ thuộc vào Illuminate.
 */
class Engine
{
    public function __construct(private Compiler $compiler)
    {
    }

    /**
     * Lấy nội dung đã được render của một view.
     *
     * @param string $path Đường dẫn đến file template gốc.
     * @param array $data Dữ liệu truyền cho view.
     * @param ViewFactory $factory Instance của ViewFactory để hỗ trợ layout.
     * @return string
     * @throws ViewException
     */
    public function get(string $path, array $data, ViewFactory $factory): string
    {
        // Nếu file là .blade.php, chúng ta sẽ biên dịch nó.
        if (str_ends_with($path, '.blade.php')) {
            if ($this->compiler->isExpired($path)) {
                $this->compiler->compile($path);
            }
            $path = $this->compiler->getCompiledPath($path);
        }

        // Make ViewFactory instance available as $factory inside the view
        $data['factory'] = $factory;

        // Extract data variables into the local scope
        extract($data);

        try {
            ob_start();
            include $path; // Include the compiled view file
        } catch (Throwable $e) {
            // Clean up and re-throw exception
            $factory->flush();
            ob_end_clean(); // Ensure buffer is cleaned on error
            throw $e;
        }

        return ob_get_clean();
    }
}
