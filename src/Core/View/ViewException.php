<?php

namespace Core\View;

use Exception;
use Throwable;

class ViewException extends Exception
{
    /**
     * @var string The path to the original view file.
     */
    protected string $viewPath;

    /**
     * @var int The line number in the original view file.
     */
    protected int $viewLine;

    public function __construct(string $message, string $viewPath, int $viewLine, Throwable $previous)
    {
        $this->viewPath = $viewPath;
        $this->viewLine = $viewLine;

        // Construct a more informative message
        $finalMessage = sprintf('%s in %s (line %d)', $message, $viewPath, $viewLine);

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
