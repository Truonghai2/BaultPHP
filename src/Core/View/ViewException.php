<?php

namespace Core\View;

use Exception;
use Throwable;

/**
 * Class ViewException
 *
 * Một exception tùy chỉnh được ném ra khi có lỗi trong quá trình render view.
 * Nó chứa thông tin về đường dẫn và dòng lỗi trong file template gốc,
 * giúp việc debug dễ dàng hơn rất nhiều.
 *
 * @package Core\View
 */
class ViewException extends Exception
{
    public function __construct(
        string $message,
        private string $viewPath,
        private int $viewLine,
        Throwable $previous,
    ) {
        $finalMessage = sprintf('%s in view %s (line %d)', $message, $viewPath, $viewLine);
        parent::__construct($finalMessage, 0, $previous);
    }

    public function getViewPath(): string
    {
        return $this->viewPath;
    }

    public function getViewLine(): int
    {
        return $this->viewLine;
    }
}
