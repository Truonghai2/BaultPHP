<?php

namespace Tests;

use Core\AppKernel;
use Core\Application;
use Core\Contracts\StatefulService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCases extends BaseTestCase
{
    protected Application $app;

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = new Application(
            $_ENV['APP_BASE_PATH'] ?? realpath(__DIR__ . '/../'),
        );
        $kernel = new AppKernel($app);
        $app = $kernel->getApplication();

        // Đọc cấu hình database từ file config thay vì hard-code
        // Điều này cho phép bạn định nghĩa một connection 'testing' riêng
        $config = $app->make('config');
        $defaultConnection = $config->get('database.default');
        $testingConnection = $config->get('database.connections.testing');

        if ($testingConnection) {
            $config->set('database.default', 'testing');
        }

        return $app;
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        if (!isset($this->app)) {
            $this->app = $this->createApplication();
        }

        // If the test class uses the RefreshDatabase trait, boot it.
        if (method_exists($this, 'bootRefreshDatabase')) {
            $this->bootRefreshDatabase();
        }
    }

    /**
     * Clean up the testing environment before the next test.
     * This is crucial for resetting stateful services.
     */
    protected function tearDown(): void
    {
        // Reset any stateful services that were resolved during the test.
        $resolved = $this->app->getResolved();
        foreach ($resolved as $abstract => $isShared) {
            if ($isShared && ($instance = $this->app->get($abstract)) instanceof StatefulService) {
                $instance->resetState();
            }
        }
        parent::tearDown();
    }

    /**
     * Simulate a GET request.
     */
    public function get(string $uri, array $headers = [])
    {
        return $this->call('GET', $uri, [], [], [], $headers);
    }

    /**
     * Simulate a POST request.
     */
    public function post(string $uri, array $data = [], array $headers = [])
    {
        return $this->call('POST', $uri, $data, [], [], $headers);
    }

    /**
     * Call the given URI with the given parameters.
     */
    public function call(string $method, string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], ?string $content = null): ResponseInterface
    {
        $factory = new Psr17Factory();

        $serverParams = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'SERVER_PROTOCOL' => '1.1',
            'HTTP_HOST' => 'localhost',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ], $server);

        $request = $factory->createServerRequest($method, $uri, $serverParams);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $request = $request->withParsedBody($parameters);
        } else {
            $request = $request->withQueryParams($parameters);
        }

        $request = $request->withCookieParams($cookies)
                           ->withUploadedFiles($files);

        // Bind the request to the container so it can be injected
        $this->app->instance(ServerRequestInterface::class, $request);

        return $this->app->make(\Core\Contracts\Http\Kernel::class)->handle($request);
    }
}
