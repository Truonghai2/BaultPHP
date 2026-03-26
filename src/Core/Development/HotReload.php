<?php

declare(strict_types=1);

namespace Core\Development;

use Core\Support\Facades\Log;
use Symfony\Component\Finder\Finder;

/**
 * Hot Reload với Advanced Features
 *
 * Enhanced file watching với:
 * - Smart file watching
 * - Dependency tracking
 * - Incremental compilation
 * - Fast refresh
 *
 * Features:
 * - Smart file watching
 * - Dependency tracking
 * - Incremental compilation
 * - Fast refresh
 */
class HotReload
{
    protected array $fileStates = [];
    protected array $dependencies = [];
    protected array $compiledFiles = [];
    protected array $changedFiles = [];
    protected ?Finder $finder = null;

    public function __construct(
        protected array $config = [],
    ) {
        $this->loadDependencies();
    }

    /**
     * Watch files for changes
     */
    public function watch(): void
    {
        $interval = $this->config['interval'] ?? 500; // milliseconds
        $directories = $this->config['directories'] ?? [];
        $ignorePatterns = $this->config['ignore'] ?? [];

        Log::info("Hot reload watcher started", [
            'directories' => $directories,
            'interval' => $interval,
        ]);

        while (true) {
            $this->checkForChanges($directories, $ignorePatterns);
            usleep($interval * 1000); // Convert to microseconds
        }
    }

    /**
     * Check for file changes
     */
    protected function checkForChanges(array $directories, array $ignorePatterns): void
    {
        $newStates = $this->scanFiles($directories, $ignorePatterns);
        $changes = $this->detectChanges($newStates);

        if (empty($changes)) {
            return;
        }

        // Track changed files
        foreach ($changes as $change) {
            $this->changedFiles[] = $change;
        }

        // Find dependent files
        $affectedFiles = $this->findAffectedFiles($changes);

        // Incremental compilation
        $this->compileIncremental($affectedFiles);

        // Fast refresh
        $this->fastRefresh($affectedFiles);

        // Update file states
        $this->fileStates = $newStates;

        Log::info("Hot reload triggered", [
            'changes' => count($changes),
            'affected_files' => count($affectedFiles),
        ]);
    }

    /**
     * Scan files in directories
     */
    protected function scanFiles(array $directories, array $ignorePatterns): array
    {
        $fileStates = [];

        if (!$this->finder) {
            $this->finder = new Finder();
            $this->finder->files()
                ->ignoreDotFiles(true)
                ->exclude($ignorePatterns);
        }

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            try {
                $files = $this->finder->in($directory);
                
                foreach ($files as $file) {
                    $path = $file->getPathname();
                    $fileStates[$path] = [
                        'mtime' => $file->getMTime(),
                        'size' => $file->getSize(),
                        'hash' => hash_file('md5', $path),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("Error scanning directory", [
                    'directory' => $directory,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $fileStates;
    }

    /**
     * Detect changes in files
     */
    protected function detectChanges(array $newStates): array
    {
        $changes = [];

        // Check for new/modified files
        foreach ($newStates as $path => $state) {
            if (!isset($this->fileStates[$path])) {
                $changes[] = [
                    'type' => 'created',
                    'file' => $path,
                    'state' => $state,
                ];
            } elseif ($this->fileStates[$path]['hash'] !== $state['hash']) {
                $changes[] = [
                    'type' => 'modified',
                    'file' => $path,
                    'state' => $state,
                ];
            }
        }

        // Check for deleted files
        foreach ($this->fileStates as $path => $state) {
            if (!isset($newStates[$path])) {
                $changes[] = [
                    'type' => 'deleted',
                    'file' => $path,
                ];
            }
        }

        return $changes;
    }

    /**
     * Find files affected by changes
     */
    protected function findAffectedFiles(array $changes): array
    {
        $affectedFiles = [];

        foreach ($changes as $change) {
            $file = $change['file'];
            
            // Add changed file itself
            $affectedFiles[$file] = true;

            // Find files that depend on this file
            $dependents = $this->getDependents($file);
            foreach ($dependents as $dependent) {
                $affectedFiles[$dependent] = true;
            }
        }

        return array_keys($affectedFiles);
    }

    /**
     * Get files that depend on a given file
     */
    protected function getDependents(string $file): array
    {
        $dependents = [];

        // Check dependency map
        if (isset($this->dependencies[$file])) {
            $dependents = array_merge($dependents, $this->dependencies[$file]);
        }

        // Auto-detect dependencies by parsing files
        $detectedDeps = $this->detectDependencies($file);
        foreach ($detectedDeps as $dep) {
            if (isset($this->dependencies[$dep])) {
                $dependents = array_merge($dependents, $this->dependencies[$dep]);
            }
        }

        return array_unique($dependents);
    }

    /**
     * Detect dependencies by parsing file
     */
    protected function detectDependencies(string $file): array
    {
        $dependencies = [];
        
        if (!file_exists($file)) {
            return $dependencies;
        }

        $content = file_get_contents($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // PHP files - detect use statements, require, include
        if ($extension === 'php') {
            // Match use statements
            if (preg_match_all('/use\s+([^;]+);/', $content, $matches)) {
                foreach ($matches[1] as $use) {
                    $use = trim($use);
                    // Convert namespace to file path
                    $filePath = $this->namespaceToPath($use);
                    if ($filePath) {
                        $dependencies[] = $filePath;
                    }
                }
            }

            // Match require/include statements
            if (preg_match_all('/(?:require|include)(?:_once)?\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $include) {
                    $resolved = $this->resolveIncludePath($include, $file);
                    if ($resolved) {
                        $dependencies[] = $resolved;
                    }
                }
            }
        }

        return $dependencies;
    }

    /**
     * Convert namespace to file path
     */
    protected function namespaceToPath(string $namespace): ?string
    {
        // Remove leading backslash
        $namespace = ltrim($namespace, '\\');
        
        // Try common paths
        $paths = [
            base_path('src/' . str_replace('\\', '/', $namespace) . '.php'),
            base_path('Modules/' . str_replace('\\', '/', $namespace) . '.php'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Resolve include path relative to current file
     */
    protected function resolveIncludePath(string $include, string $currentFile): ?string
    {
        $dir = dirname($currentFile);
        $path = $dir . '/' . $include;

        if (file_exists($path)) {
            return realpath($path);
        }

        return null;
    }

    /**
     * Incremental compilation
     */
    protected function compileIncremental(array $files): void
    {
        foreach ($files as $file) {
            if (isset($this->compiledFiles[$file])) {
                // File already compiled, skip
                continue;
            }

            // Compile file
            $this->compileFile($file);
            $this->compiledFiles[$file] = time();
        }
    }

    /**
     * Compile a single file
     */
    protected function compileFile(string $file): void
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        match ($extension) {
            'php' => $this->compilePhp($file),
            'blade' => $this->compileBlade($file),
            default => null,
        };
    }

    /**
     * Compile PHP file (clear opcache)
     */
    protected function compilePhp(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Compile Blade template
     */
    protected function compileBlade(string $file): void
    {
        // Clear Blade cache
        $compiledPath = storage_path('framework/views/' . md5($file) . '.php');
        if (file_exists($compiledPath)) {
            unlink($compiledPath);
        }
    }

    /**
     * Fast refresh - reload only affected parts
     */
    protected function fastRefresh(array $files): void
    {
        // Group files by type
        $phpFiles = [];
        $configFiles = [];
        $viewFiles = [];

        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            
            match ($extension) {
                'php' => $phpFiles[] = $file,
                'php' => (str_contains($file, 'config/') ? $configFiles[] = $file : null),
                'blade', 'php' => (str_contains($file, 'resources/views/') ? $viewFiles[] = $file : null),
                default => null,
            };
        }

        // Refresh PHP files (clear opcache)
        if (!empty($phpFiles)) {
            foreach ($phpFiles as $file) {
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file, true);
                }
            }
        }

        // Refresh config files
        if (!empty($configFiles)) {
            // Clear config cache
            if (file_exists($cachePath = bootstrap_path('cache/config.php'))) {
                unlink($cachePath);
            }
        }

        // Refresh view files
        if (!empty($viewFiles)) {
            // Clear view cache
            $viewCachePath = storage_path('framework/views');
            if (is_dir($viewCachePath)) {
                $this->clearDirectory($viewCachePath);
            }
        }
    }

    /**
     * Clear directory
     */
    protected function clearDirectory(string $directory): void
    {
        $files = glob($directory . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Load dependencies from cache or build
     */
    protected function loadDependencies(): void
    {
        $cachePath = storage_path('framework/cache/dependencies.php');
        
        if (file_exists($cachePath)) {
            $this->dependencies = require $cachePath;
        } else {
            $this->buildDependencyMap();
        }
    }

    /**
     * Build dependency map
     */
    protected function buildDependencyMap(): void
    {
        $directories = $this->config['directories'] ?? [];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = glob($directory . '/**/*.php');
            
            foreach ($files as $file) {
                $deps = $this->detectDependencies($file);
                if (!empty($deps)) {
                    foreach ($deps as $dep) {
                        if (!isset($this->dependencies[$dep])) {
                            $this->dependencies[$dep] = [];
                        }
                        $this->dependencies[$dep][] = $file;
                    }
                }
            }
        }

        // Cache dependencies
        $cachePath = storage_path('framework/cache/dependencies.php');
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cachePath, '<?php return ' . var_export($this->dependencies, true) . ';');
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'files_watched' => count($this->fileStates),
            'dependencies_tracked' => count($this->dependencies),
            'files_compiled' => count($this->compiledFiles),
            'recent_changes' => count(array_slice($this->changedFiles, -10)),
        ];
    }
}
