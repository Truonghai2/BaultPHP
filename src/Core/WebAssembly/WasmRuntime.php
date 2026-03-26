<?php

declare(strict_types=1);

namespace Core\WebAssembly;

use Core\Support\Facades\Log;
use RuntimeException;

/**
 * WASM Runtime wrapper
 *
 * Supports multiple WASM runtimes:
 * - Wasmtime (Rust-based, recommended)
 * - Wasmer (Rust-based)
 * - WAVM (C++-based)
 */
class WasmRuntime
{
    protected ?string $runtimePath = null;
    protected string $runtimeType = 'wasmtime';
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->runtimeType = $config['runtime'] ?? 'wasmtime';
        $this->runtimePath = $config['runtime_path'] ?? null;
        
        $this->detectRuntime();
    }

    /**
     * Detect available WASM runtime
     */
    protected function detectRuntime(): void
    {
        if ($this->runtimePath && file_exists($this->runtimePath)) {
            return;
        }

        // Try to find runtime in common locations
        $commonPaths = [
            '/usr/local/bin/wasmtime',
            '/usr/bin/wasmtime',
            getenv('HOME') . '/.cargo/bin/wasmtime',
            base_path('vendor/bin/wasmtime'),
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $this->runtimePath = $path;
                Log::info("WASM Runtime detected: {$path}");
                return;
            }
        }

        // Check if PHP extension is available
        if (extension_loaded('wasm')) {
            $this->runtimeType = 'php-ext';
            Log::info("PHP WASM extension detected");
            return;
        }

        Log::warning("No WASM runtime detected. WASM features will be disabled.");
    }

    /**
     * Check if WASM runtime is available
     */
    public function isAvailable(): bool
    {
        return $this->runtimeType === 'php-ext' || 
               ($this->runtimePath !== null && file_exists($this->runtimePath));
    }

    /**
     * Execute WASM module
     *
     * @param string $wasmFile Path to WASM file
     * @param array $inputs Input parameters
     * @param array $options Additional options
     * @return mixed
     */
    public function execute(string $wasmFile, array $inputs = [], array $options = []): mixed
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('WASM runtime is not available. Please install wasmtime or enable PHP WASM extension.');
        }

        if (!file_exists($wasmFile)) {
            throw new RuntimeException("WASM file not found: {$wasmFile}");
        }

        if ($this->runtimeType === 'php-ext') {
            return $this->executeWithPhpExt($wasmFile, $inputs, $options);
        }

        return $this->executeWithExternalRuntime($wasmFile, $inputs, $options);
    }

    /**
     * Execute using PHP WASM extension
     */
    protected function executeWithPhpExt(string $wasmFile, array $inputs, array $options): mixed
    {
        // PHP WASM extension API
        $wasm = wasm_module_new($wasmFile);
        if (!$wasm) {
            throw new RuntimeException("Failed to load WASM module: {$wasmFile}");
        }

        $instance = wasm_instance_new($wasm);
        if (!$instance) {
            throw new RuntimeException("Failed to create WASM instance");
        }

        // Call function
        $functionName = $options['function'] ?? 'main';
        $result = wasm_func_call($instance, $functionName, $inputs);

        return $result;
    }

    /**
     * Execute using external runtime (wasmtime)
     */
    protected function executeWithExternalRuntime(string $wasmFile, array $inputs, array $options): mixed
    {
        $ioMode = $options['io_mode'] ?? ($this->config['plugin_abi'] ?? 'invoke');
        if ($ioMode === 'stdio') {
            return $this->executeWithStdio($wasmFile, $inputs, $options);
        }
        return $this->executeWithInvoke($wasmFile, $inputs, $options);
    }

    /** Plugin ABI: JSON on stdin, JSON on stdout (WASI). */
    protected function executeWithStdio(string $wasmFile, array $inputs, array $options): mixed
    {
        $timeout = (int) ($options['timeout'] ?? 30);
        $command = sprintf('%s run %s', escapeshellarg($this->runtimePath), escapeshellarg($wasmFile));
        $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to execute WASM runtime');
        }
        $inputJson = json_encode($inputs);
        if ($inputJson === false) {
            fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
            throw new RuntimeException('Invalid JSON input for WASM');
        }
        fwrite($pipes[0], $inputJson);
        fclose($pipes[0]);
        $output = $this->readStreamWithTimeout($pipes[1], $timeout, $process);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $returnCode = proc_close($process);
        if ($returnCode !== 0) {
            throw new RuntimeException('WASM execution failed: ' . trim($error ?: 'non-zero exit'));
        }
        return $this->parseOutput($output, $options);
    }

    /** Invoke: wasmtime run module.wasm --invoke <func> <json-arg> */
    protected function executeWithInvoke(string $wasmFile, array $inputs, array $options): mixed
    {
        $functionName = $options['function'] ?? 'run';
        $timeout = (int) ($options['timeout'] ?? 30);
        $singleArg = json_encode($inputs);
        if ($singleArg === false) {
            $singleArg = '{}';
        }
        $command = sprintf(
            '%s run %s --invoke %s %s',
            escapeshellarg($this->runtimePath),
            escapeshellarg($wasmFile),
            escapeshellarg($functionName),
            escapeshellarg($singleArg)
        );
        $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to execute WASM runtime');
        }
        fclose($pipes[0]);
        $output = $this->readStreamWithTimeout($pipes[1], $timeout, $process);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $returnCode = proc_close($process);
        if ($returnCode !== 0) {
            throw new RuntimeException('WASM execution failed: ' . trim($error ?: 'non-zero exit'));
        }
        return $this->parseOutput($output, $options);
    }

    private function readStreamWithTimeout($stream, int $timeoutSeconds, $process): string
    {
        $start = time();
        $output = '';
        stream_set_blocking($stream, false);
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $output .= stream_get_contents($stream);
                break;
            }
            if (time() - $start > $timeoutSeconds) {
                proc_terminate($process);
                throw new RuntimeException("WASM execution timeout after {$timeoutSeconds} seconds");
            }
            $chunk = stream_get_contents($stream);
            if ($chunk !== '') {
                $output .= $chunk;
            }
            usleep(10000);
        }
        return $output;
    }

    /**
     * Parse runtime output
     */
    protected function parseOutput(string $output, array $options): mixed
    {
        $format = $options['output_format'] ?? 'json';
        
        $output = trim($output);
        
        if (empty($output)) {
            return null;
        }

        switch ($format) {
            case 'json':
                $decoded = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return $output;
            
            case 'int':
                return (int)$output;
            
            case 'float':
                return (float)$output;
            
            case 'bool':
                return filter_var($output, FILTER_VALIDATE_BOOLEAN);
            
            default:
                return $output;
        }
    }

    /**
     * Validate WASM file
     */
    public function validate(string $wasmFile): bool
    {
        if (!file_exists($wasmFile)) {
            return false;
        }

        // Check magic number
        $handle = fopen($wasmFile, 'rb');
        if (!$handle) {
            return false;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        // WASM magic number: \0asm
        return $magic === "\x00\x61\x73\x6D";
    }

    /**
     * Get runtime information
     */
    public function getInfo(): array
    {
        return [
            'type' => $this->runtimeType,
            'path' => $this->runtimePath,
            'available' => $this->isAvailable(),
        ];
    }
}
