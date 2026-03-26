<?php

declare(strict_types=1);

namespace Core\WebAssembly;

use Core\Application;
use Core\Support\Facades\Log;
use RuntimeException;

/**
 * WASM Executor
 *
 * High-level interface for executing WASM modules.
 * Provides caching, error handling, and performance monitoring.
 */
class WasmExecutor
{
    protected WasmRuntime $runtime;
    protected array $moduleCache = [];
    protected array $config = [];

    public function __construct(
        Application $app,
        ?WasmRuntime $runtime = null,
    ) {
        $this->config = config('wasm', []);
        $this->runtime = $runtime ?? new WasmRuntime($this->config);
    }

    /**
     * Execute WASM module
     *
     * @param string $wasmFile Path to WASM file or module name
     * @param array $inputs Input parameters
     * @param array $options Execution options
     * @return mixed
     */
    public function execute(string $wasmFile, array $inputs = [], array $options = []): mixed
    {
        $startTime = microtime(true);

        try {
            // Resolve WASM file path
            $resolvedPath = $this->resolveWasmFile($wasmFile);
            
            // Validate WASM file
            if (!$this->runtime->validate($resolvedPath)) {
                throw new RuntimeException("Invalid WASM file: {$resolvedPath}");
            }

            // Check cache if enabled
            if ($this->config['cache_enabled'] ?? true) {
                $cacheKey = $this->getCacheKey($resolvedPath, $inputs, $options);
                $cached = $this->getFromCache($cacheKey);
                if ($cached !== null) {
                    Log::debug("WASM cache hit", ['module' => $wasmFile]);
                    return $cached;
                }
            }

            // Execute WASM module
            $result = $this->runtime->execute($resolvedPath, $inputs, $options);

            // Cache result if enabled
            if ($this->config['cache_enabled'] ?? true) {
                $this->putToCache($cacheKey, $result);
            }

            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::debug("WASM execution completed", [
                'module' => $wasmFile,
                'execution_time_ms' => round($executionTime, 2),
            ]);

            return $result;

        } catch (\Throwable $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::error("WASM execution failed", [
                'module' => $wasmFile,
                'error' => $e->getMessage(),
                'execution_time_ms' => round($executionTime, 2),
            ]);

            // Fallback to PHP implementation if configured
            if ($this->config['fallback_to_php'] ?? false) {
                return $this->fallbackToPhp($wasmFile, $inputs, $options);
            }

            throw $e;
        }
    }

    /**
     * Resolve WASM file path.
     * Supports: absolute path, registered "modules" key, "ModuleName/plugin" -> Modules/ModuleName/wasm/plugin.wasm, wasm_directory.
     */
    protected function resolveWasmFile(string $wasmFile): string
    {
        if (str_starts_with($wasmFile, '/') || (str_starts_with($wasmFile, '\\'))) {
            return $wasmFile;
        }

        $modules = $this->config['modules'] ?? [];
        if (isset($modules[$wasmFile])) {
            return $modules[$wasmFile];
        }

        // Module plugin: "ModuleName/plugin" or "ModuleName/plugin.wasm"
        if (str_contains($wasmFile, '/')) {
            $parts = explode('/', $wasmFile, 2);
            $moduleName = $parts[0];
            $name = $parts[1];
            $wasmSubdir = $this->config['module_wasm_dir'] ?? 'wasm';
            $moduleWasmDir = base_path('Modules/' . $moduleName . '/' . $wasmSubdir);
            if (is_dir($moduleWasmDir)) {
                $path = $moduleWasmDir . '/' . $name;
                if (file_exists($path)) {
                    return $path;
                }
                if (!str_ends_with($name, '.wasm')) {
                    $path = $moduleWasmDir . '/' . $name . '.wasm';
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }
        }

        $wasmDir = $this->config['wasm_directory'] ?? base_path('wasm');
        $path = $wasmDir . '/' . $wasmFile;
        if (file_exists($path)) {
            return $path;
        }
        if (!str_ends_with($wasmFile, '.wasm')) {
            $path = $wasmDir . '/' . $wasmFile . '.wasm';
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new RuntimeException("WASM file not found: {$wasmFile}");
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $wasmFile, array $inputs, array $options): string
    {
        $key = $wasmFile . '|' . serialize($inputs) . '|' . serialize($options);
        return 'wasm:' . hash('sha256', $key);
    }

    /**
     * Get from cache
     */
    protected function getFromCache(string $key): mixed
    {
        if (!($this->config['cache_enabled'] ?? true)) {
            return null;
        }

        $cache = cache();
        return $cache->get($key);
    }

    /**
     * Put to cache
     */
    protected function putToCache(string $key, mixed $value): void
    {
        if (!($this->config['cache_enabled'] ?? true)) {
            return;
        }

        $ttl = $this->config['cache_ttl'] ?? 3600;
        cache()->set($key, $value, $ttl);
    }

    /**
     * Fallback to PHP implementation
     */
    protected function fallbackToPhp(string $wasmFile, array $inputs, array $options): mixed
    {
        Log::warning("Falling back to PHP implementation", ['module' => $wasmFile]);
        
        // Try to find PHP fallback implementation
        $fallbackClass = $this->config['fallbacks'][$wasmFile] ?? null;
        if ($fallbackClass && class_exists($fallbackClass)) {
            $fallback = new $fallbackClass();
            if (method_exists($fallback, 'execute')) {
                return $fallback->execute($inputs, $options);
            }
        }

        throw new RuntimeException("No fallback implementation available for: {$wasmFile}");
    }

    /**
     * Register WASM module
     */
    public function registerModule(string $name, string $path): void
    {
        $modules = $this->config['modules'] ?? [];
        $modules[$name] = $path;
        $this->config['modules'] = $modules;
    }

    /**
     * Check if WASM is available
     */
    public function isAvailable(): bool
    {
        return $this->runtime->isAvailable();
    }

    /**
     * Get runtime info
     */
    public function getRuntimeInfo(): array
    {
        return $this->runtime->getInfo();
    }

    /**
     * List WASM files in a module's wasm directory (Modules/<name>/wasm/*.wasm).
     *
     * @return array<string, string> basename (.wasm) => full path
     */
    public function listModuleWasm(string $moduleName): array
    {
        $wasmSubdir = $this->config['module_wasm_dir'] ?? 'wasm';
        $dir = base_path('Modules/' . $moduleName . '/' . $wasmSubdir);
        $list = [];
        if (!is_dir($dir)) {
            return $list;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path) && str_ends_with($entry, '.wasm')) {
                $list[$entry] = $path;
            }
        }
        return $list;
    }
}
