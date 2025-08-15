<?php

namespace Tests;

use Core\AppKernel;
use Core\Application;
use Http\Request;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        // Set the DB_CONNECTION environment variable for testing
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';

        $app = new Application(
            $_ENV['APP_BASE_PATH'] ?? realpath(__DIR__ . '/../'),
        );
        $kernel = new AppKernel($app);
        return $kernel->getApplication();
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
    public function call(string $method, string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], string $content = null)
    {
        $request = Request::create($uri, $method, $parameters, $cookies, $files, $server, $content);

        // Bind the request to the container so it can be injected
        $this->app->instance(Request::class, $request);

        return $this->app->make(\Core\Contracts\Http\Kernel::class)->handle($request);
    }
}
