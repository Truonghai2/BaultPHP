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
     * The currently authenticated user.
     */
    protected ?\Core\Contracts\Auth\Authenticatable $authenticatedUser = null;
    
    /**
     * Headers to be sent with the next request.
     */
    protected array $defaultHeaders = [];

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
     * Set the authenticated user for the next request.
     */
    public function actingAs(\Core\Contracts\Auth\Authenticatable $user, string $guard = 'web'): self
    {
        $this->authenticatedUser = $user;
        
        // Set user in the auth guard
        $authManager = $this->app->make(\Core\Auth\AuthManager::class);
        $guardInstance = $authManager->guard($guard);
        $guardInstance->setUser($user);
        
        // Also set in session for session-based guards
        if ($guardInstance instanceof \Core\Auth\SessionGuard) {
            $session = $this->app->make(\Core\Contracts\Session\SessionInterface::class);
            $sessionKey = $guardInstance->getName();
            $session->set($sessionKey, $user->getAuthIdentifier());
        }
        
        return $this;
    }

    /**
     * Set headers for the next request.
     */
    public function withHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Simulate a GET request.
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        $response = $this->call('GET', $uri, [], [], [], array_merge($this->defaultHeaders, $headers));
        $this->defaultHeaders = []; // Reset after request
        return new TestResponse($response, $this->app);
    }

    /**
     * Simulate a POST request.
     */
    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $response = $this->call('POST', $uri, $data, [], [], array_merge($this->defaultHeaders, $headers));
        $this->defaultHeaders = []; // Reset after request
        return new TestResponse($response, $this->app);
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
