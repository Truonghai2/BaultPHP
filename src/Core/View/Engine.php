<?php

namespace Core\View;

use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * Class Engine
 *
 * Manages the compilation, caching, and rendering of view files.
 */
class Engine
{
    public function __construct(
        private Compiler $compiler,
        private Filesystem $files,
        private string $cachePath
    ) {
    }

    /**
     * Get the evaluated content of a view.
     */
    public function get(string $path, array $data, ViewFactory $factory): string
    {
        $compiledPath = $this->getCompiledPath($path);

        // If the compiled file is expired, re-compile it.
        if ($this->isExpired($path, $compiledPath)) {
            $content = $this->files->get($path);
            $compiledContent = $this->compiler->compile($content, $path);
            $this->files->put($compiledPath, $compiledContent);
        }

        return $this->evaluatePath($compiledPath, $data, $factory);
    }

    /**
     * Get the path to the compiled version of a view.
     */
    protected function getCompiledPath(string $path): string
    {
        return $this->cachePath . '/' . sha1($path) . '.php';
    }

    /**
     * Determine if the view at the given path is expired.
     */
    protected function isExpired(string $path, string $compiledPath): bool
    {
        if (!$this->files->exists($compiledPath)) {
            return true;
        }

        return $this->files->lastModified($path) >= $this->files->lastModified($compiledPath);
    }

    /**
     * Get the evaluated content of the view at the given path.
     */
    protected function evaluatePath(string $path, array $data, ViewFactory $factory): string
    {
        $obLevel = ob_get_level();
        ob_start();

        // Make variables available to the view.
        extract($data, EXTR_SKIP);

        // Create a closure that includes the file and bind it to the factory instance.
        // This makes `$this` inside the template refer to the factory,
        // allowing access to methods like `extend`, `section`, `yield`, etc.
        $evaluator = function () use ($path) {
            include $path;
        };

        // Bind the closure and execute it.
        $boundEvaluator = $evaluator->bindTo($factory, $factory);

        try {
            $boundEvaluator();
        } catch (Throwable $e) {
            $this->handleViewException($e, $obLevel);
        }

        return ltrim(ob_get_clean());
    }

    /**
     * Handle a throwable exception thrown during view evaluation.
     *
     * @throws \Core\View\ViewException
     */
    protected function handleViewException(Throwable $e, int $obLevel): void
    {
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }

        $compiledPath = $e->getFile();
        $compiledContent = $this->files->get($compiledPath);

        // Find the original view path from the source comment
        preg_match('/^\s*<\?php\s*\/\*\s*Source:\s*(.*?)\s*\*\//', $compiledContent, $sourceMatches);
        $viewPath = $sourceMatches[1] ?? $compiledPath;

        // Find the original line number by looking for the last /* line X */ comment
        // before the error line in the compiled file.
        $compiledLines = explode("\n", $compiledContent);
        $errorLine = $e->getLine();
        $viewLine = 0;
        for ($i = 0; $i < $errorLine; $i++) {
            if (preg_match('/^\s*<\?php\s*\/\*\s*line\s*(\d+)\s*\*\//', $compiledLines[$i], $lineMatches)) {
                $viewLine = (int) $lineMatches[1];
            }
        }

        throw new ViewException($e->getMessage(), $viewPath, $viewLine, $e);
    }
}
