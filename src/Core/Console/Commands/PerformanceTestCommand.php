<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Routing\Router;
use Core\Support\Benchmark;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;

class PerformanceTestCommand extends BaseCommand
{
    public function __construct(Application
     $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'performance:test';
    }

    public function description(): string
    {
        return 'Run framework performance tests.';
    }

    public function handle(): int
    {
        $this->info('Running BaultPHP Performance Tests...');
        $this->line('');

        $iterations = 100000;

        // Test 1: Framework Bootstrap
        $this->runBenchmark('Framework Bootstrap', $iterations, function () {
            // This is a simplified bootstrap. A real one might involve more.
            // We are measuring the DI container instantiation.
            $app = new Application(base_path());
        });

        // Test 2: DI Container - Singleton Resolution
        $app = Application::getInstance();
        $app->singleton('test_singleton', fn () => new \stdClass());
        $this->runBenchmark('DI Singleton Resolution', $iterations, function () use ($app) {
            $app->make('test_singleton');
        });

        // Test 3: DI Container - Transient Resolution
        $app->bind('test_transient', fn () => new \stdClass());
        $this->runBenchmark('DI Transient Resolution', $iterations, function () use ($app) {
            $app->make('test_transient');
        });

        // Test 4: Router Dispatch
        /** @var Router $router */
        $router = $app->make(Router::class);
        // Assuming you have a home route defined
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', new Uri('/'));

        $this->runBenchmark('Router Dispatch (Home)', $iterations, function () use ($router, $request) {
            try {
                $router->dispatch($request);
            } catch (\Exception $e) {
                // Ignore routing errors for this test, we are just measuring dispatch time
            }
        });

        // Test 5: Database Connection (Raw Query)
        // Bài test này giả định rằng bạn đã cấu hình kết nối CSDL trong file .env
        // và 'db' là binding cho database manager trong DI container.
        $this->runBenchmark('Database Connection (Raw Query)', $iterations, function () use ($app) {
            try {
                // Thực thi một truy vấn thô (raw query) đơn giản nhất để đo lường
                // thời gian round-trip đến CSDL.
                $app->make('db')->statement('SELECT 1');
            } catch (\Exception $e) {
                // Bỏ qua lỗi nếu CSDL chưa được cấu hình, để các bài test khác vẫn chạy.
            }
        });

        $this->info('Performance tests completed.');
        return 0;
    }

    protected function runBenchmark(string $name, int $iterations, \Closure $callback): void
    {
        $this->comment("Benchmarking '{$name}' ({$iterations} iterations)...");

        Benchmark::start($name);
        for ($i = 0; $i < $iterations; $i++) {
            $callback();
        }
        $results = Benchmark::stop($name);

        $this->line('  <info>Total Time:</info>   ' . number_format($results['time'], 2) . 'ms');
        $this->line('  <info>Average Time:</info> ' . number_format($results['time'] / $iterations, 4) . 'ms per iteration');
        $this->line('  <info>Memory Usage:</info> ' . Benchmark::formatBytes($results['memory']));
        $this->line('  <info>Peak Memory:</info>  ' . Benchmark::formatBytes($results['memory_peak']));
        $this->line('');
    }
}
