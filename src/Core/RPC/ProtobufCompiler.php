<?php

declare(strict_types=1);

namespace Core\RPC;

use Core\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Protocol Buffers Compiler
 *
 * Wrapper for protoc compiler to generate PHP classes from .proto files.
 */
class ProtobufCompiler
{
    protected ?string $protocPath = null;
    protected array $config = [];

    /** Chỉ log "protoc detected" một lần mỗi process để tránh spam log trên mỗi request/page */
    private static bool $detectionLogged = false;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('grpc.protobuf', []), $config);
        $this->detectProtoc();
    }

    /**
     * Detect protoc compiler
     */
    protected function detectProtoc(): void
    {
        $customPath = $this->config['protoc_path'] ?? null;
        if ($customPath && file_exists($customPath) && is_executable($customPath)) {
            $this->protocPath = $customPath;
            if (!self::$detectionLogged && !extension_loaded('swoole')) {
                self::$detectionLogged = true;
                Log::debug("protoc detected: {$customPath}");
            }
            return;
        }

        // Try common locations
        $commonPaths = [
            '/usr/local/bin/protoc',
            '/usr/bin/protoc',
            getenv('HOME') . '/.local/bin/protoc',
            base_path('vendor/bin/protoc'),
        ];

        foreach ($commonPaths as $path) {
            if ($path !== '' && file_exists($path) && is_executable($path)) {
                $this->protocPath = $path;
                if (!self::$detectionLogged && !extension_loaded('swoole')) {
                    self::$detectionLogged = true;
                    Log::debug("protoc detected: {$path}");
                }
                return;
            }
        }

        Log::warning("protoc compiler not found. Protocol buffer compilation will be disabled.");
    }

    /**
     * Check if protoc is available
     */
    public function isAvailable(): bool
    {
        return $this->protocPath !== null;
    }

    /**
     * Compile .proto file to PHP classes
     *
     * @param string $protoFile Path to .proto file
     * @param string $outputDir Output directory for generated PHP classes
     * @param array $options Compilation options
     * @return bool Success status
     */
    public function compile(string $protoFile, string $outputDir, array $options = []): bool
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('protoc compiler is not available');
        }

        if (!file_exists($protoFile)) {
            throw new RuntimeException("Proto file not found: {$protoFile}");
        }

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $protoDir = dirname($protoFile);
        $protoName = basename($protoFile, '.proto');

        // Build protoc command
        $command = [
            $this->protocPath,
            '--php_out=' . $outputDir,
            '--grpc_out=' . $outputDir,
            '--plugin=protoc-gen-grpc=' . $this->getGrpcPluginPath(),
            '--proto_path=' . $protoDir,
            $protoFile,
        ];

        // Add import paths if specified
        $importPaths = $options['import_paths'] ?? $this->config['import_paths'] ?? [];
        foreach ($importPaths as $importPath) {
            $command[] = '--proto_path=' . $importPath;
        }

        Log::info("Compiling proto file", [
            'proto_file' => $protoFile,
            'output_dir' => $outputDir,
        ]);

        $process = new Process($command);
        $process->setTimeout($options['timeout'] ?? 300);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput();
            Log::error("Proto compilation failed", [
                'proto_file' => $protoFile,
                'error' => $error,
            ]);
            throw new RuntimeException("Proto compilation failed: {$error}");
        }

        Log::info("Proto file compiled successfully", [
            'proto_file' => $protoFile,
            'output_dir' => $outputDir,
        ]);

        return true;
    }

    /**
     * Compile all .proto files in a directory
     *
     * @param string $protoDir Directory containing .proto files
     * @param string $outputDir Output directory
     * @param array $options Compilation options
     * @return array Results for each file
     */
    public function compileDirectory(string $protoDir, string $outputDir, array $options = []): array
    {
        if (!is_dir($protoDir)) {
            throw new RuntimeException("Proto directory not found: {$protoDir}");
        }

        $results = [];
        $protoFiles = glob($protoDir . '/*.proto');

        foreach ($protoFiles as $protoFile) {
            try {
                $this->compile($protoFile, $outputDir, $options);
                $results[$protoFile] = ['success' => true];
            } catch (\Throwable $e) {
                $results[$protoFile] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get grpc_php_plugin path
     *
     * @return string
     */
    protected function getGrpcPluginPath(): string
    {
        $pluginPath = $this->config['grpc_plugin_path'] ?? null;
        if ($pluginPath && file_exists($pluginPath)) {
            return $pluginPath;
        }

        // Try common locations
        $commonPaths = [
            '/usr/local/bin/grpc_php_plugin',
            '/usr/bin/grpc_php_plugin',
            base_path('vendor/bin/grpc_php_plugin'),
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new RuntimeException('grpc_php_plugin not found. Please install gRPC PHP plugin.');
    }

    /**
     * Get protoc version
     *
     * @return string|null
     */
    public function getVersion(): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $process = new Process([$this->protocPath, '--version']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }
}
