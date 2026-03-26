<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Support\Facades\Log;
use Core\WebAssembly\WasmRuntime;
use Core\WebAssembly\WasmExecutor;
use Core\WebAssembly\WasmImageProcessor;

/**
 * WebAssembly Service Provider.
 *
 * Registers the WASM runtime and related services into the container.
 * Enables high-performance computation offloading to native binary modules.
 * WASM can be used for:
 *  - CPU-intensive image processing (alternative to GD/Imagick)
 *  - Cryptographic operations
 *  - Complex mathematical computations
 *  - Running native code safely in a sandboxed environment
 */
class WasmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core WASM Runtime - auto-detects wasmtime binary or PHP WASM extension
        $this->app->singleton(WasmRuntime::class, function ($app) {
            return new WasmRuntime(config('wasm', []));
        });

        // WASM Executor - high-level facade for running .wasm modules
        $this->app->singleton(WasmExecutor::class, function ($app) {
            return new WasmExecutor($app, $app->make(WasmRuntime::class));
        });

        // WASM Image Processor - native-speed image processing via WASM modules
        $this->app->singleton(WasmImageProcessor::class, function ($app) {
            return new WasmImageProcessor(
                $app->make(WasmRuntime::class),
                config('wasm.image_processor', []),
            );
        });

        // Register short aliases for convenience
        $this->app->alias(WasmRuntime::class, 'wasm');
        $this->app->alias(WasmExecutor::class, 'wasm.executor');
        $this->app->alias(WasmImageProcessor::class, 'wasm.image');
    }

    public function boot(): void
    {
        if (!config('wasm.enabled', false)) {
            return;
        }

        $runtime = $this->app->make(WasmRuntime::class);

        if (!$runtime->isAvailable()) {
            Log::warning('[WASM] Runtime not available. Install wasmtime or enable PHP ext-wasm. WASM features disabled.', [
                'info' => $runtime->getInfo(),
            ]);
            return;
        }

        Log::info('[WASM] Runtime ready.', ['info' => $runtime->getInfo()]);
    }
}
