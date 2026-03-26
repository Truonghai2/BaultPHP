<?php

namespace App\Providers;

use App\Services\SanitizerService;
use Core\Auth\TokenIssuerService;
use Core\Console\Commands\MakeProviderCommand;
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Contracts\Queue\Dispatcher;
use Core\Contracts\StatefulService;
use Core\Http\FormRequest;
use Core\Queue\QueueDispatcher;
use Core\Queue\QueueManager;
use Core\Redis\FiberRedisManager;
use Core\Services\HealthCheckService;
use Core\Development\HotReload;
use Core\Development\VisualDebugger;
use Core\Attributes\AttributeCodeGenerator;
use Core\Attributes\AttributeEnhancer;
use Core\Attributes\PHP83Features;
use Core\Edge\EdgeDeployer;
use Core\Edge\EdgeFunction;
use Core\Http\RequestBatcher;
use Core\Performance\JITOptimizer;
use Core\Testing\MutationTester;
use Core\Testing\PropertyTester;
use Core\Module\LazyModuleLoader;
use Core\ProviderRepository;
use Core\Support\ServiceProvider;
use Core\WebSocket\WebSocketManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        date_default_timezone_set($this->app['config']->get('app.timezone', 'UTC'));

        $this->app->singleton(KernelContract::class, \App\Http\Kernel::class);
        $this->app->tag(KernelContract::class, StatefulService::class);

        $this->app->singleton(TokenIssuerService::class, function ($app) {
            $key = config('app.key');
            return new TokenIssuerService($key);
        });

        $this->app->singleton(FiberRedisManager::class);

        $this->app->singleton(HealthCheckService::class);
        $this->app->singleton(\Core\Services\ModuleMarketplaceService::class);

        $this->app->singleton(WebSocketManager::class);

        $this->app->singleton(QueueManager::class);

        $this->app->singleton(Dispatcher::class, QueueDispatcher::class);

        $this->app->singleton(ProviderRepository::class, function ($app) {
            return new ProviderRepository($app);
        });
        $this->app->singleton(LazyModuleLoader::class, function ($app) {
            return new LazyModuleLoader($app, $app->make(ProviderRepository::class));
        });

        $this->configureFormRequestValidation();

        $this->app->singleton(SanitizerService::class);

        // Register Development Experience services
        $this->registerDevelopmentServices();

        // Register Advanced Testing services
        $this->registerTestingServices();

        // Register Performance services
        $this->registerPerformanceServices();

        // Register Modern PHP Features services
        $this->registerModernPhpServices();

        // Register Edge Computing services
        $this->registerEdgeServices();

        $this->registerCommands();
    }

    /**
     * Register development experience services
     */
    protected function registerDevelopmentServices(): void
    {
        // Register Hot Reload
        $this->app->singleton(HotReload::class, function ($app) {
            $config = config('development.hot_reload', []);
            return new HotReload($config);
        });

        // Register Visual Debugger
        $this->app->singleton(VisualDebugger::class, function ($app) {
            $config = config('development.visual_debugging', []);
            return new VisualDebugger($config);
        });
    }

    /**
     * Register advanced testing services
     */
    protected function registerTestingServices(): void
    {
        // Register Property Tester
        $this->app->singleton(PropertyTester::class, function ($app) {
            $config = config('testing.property_testing', []);
            return new PropertyTester($config);
        });

        // Register Mutation Tester
        $this->app->singleton(MutationTester::class, function ($app) {
            $config = config('testing.mutation_testing', []);
            return new MutationTester($config);
        });
    }

    /**
     * Register performance services
     */
    protected function registerPerformanceServices(): void
    {
        // Register JIT Optimizer
        $this->app->singleton(JITOptimizer::class, function ($app) {
            $config = config('performance.jit_optimization', []);
            return new JITOptimizer($config);
        });

        // Register Request Batcher
        $this->app->singleton(RequestBatcher::class, function ($app) {
            $config = config('performance.request_batching', []);
            return new RequestBatcher($config);
        });
    }

    /**
     * Register modern PHP features services
     */
    protected function registerModernPhpServices(): void
    {
        // Register Attribute Enhancer
        $this->app->singleton(AttributeEnhancer::class, function ($app) {
            return new AttributeEnhancer();
        });

        // Register PHP 8.3+ Features
        $this->app->singleton(PHP83Features::class, function ($app) {
            return new PHP83Features();
        });

        // Register Attribute Code Generator
        $this->app->singleton(AttributeCodeGenerator::class, function ($app) {
            $enhancer = $app->make(AttributeEnhancer::class);
            return new AttributeCodeGenerator($enhancer);
        });
    }

    /**
     * Register edge computing services
     */
    protected function registerEdgeServices(): void
    {
        // Register Edge Function
        $this->app->singleton(EdgeFunction::class, function ($app) {
            $config = config('edge-computing', []);
            return new EdgeFunction($config);
        });

        // Register Edge Deployer
        $this->app->singleton(EdgeDeployer::class, function ($app) {
            $edgeFunction = $app->make(EdgeFunction::class);
            $config = config('edge-computing', []);
            return new EdgeDeployer($edgeFunction, $config);
        });
    }

    protected function configureFormRequestValidation(): void
    {
        $this->app->afterResolving(FormRequest::class, function (FormRequest $request) {
            $request->validateResolved();
        });
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(MakeProviderCommand::class);
            $this->app->tag(MakeProviderCommand::class, 'console.command');
        }
    }
}
