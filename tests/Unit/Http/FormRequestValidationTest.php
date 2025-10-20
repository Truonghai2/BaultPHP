<?php

namespace Tests\Unit\Http;

use Core\Application;
use Core\Contracts\Session\SessionInterface;
use Core\Cookie\CookieManager;
use Core\Exceptions\HttpResponseException;
use Core\Http\RedirectResponse;
use Core\Http\Redirector;
use Core\Routing\Router;
use Core\Validation\Factory as ValidatorFactory;
use Modules\User\Http\Requests\LoginRequest;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Mockery;

class FormRequestValidationTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(dirname(__DIR__, 3));

        $this->mockDependencies();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockDependencies(): void
    {
        $redirectResponseMock = Mockery::mock(RedirectResponse::class)->makePartial();
        $redirectResponseMock->shouldReceive('withErrors')->andReturnSelf();
        $redirectResponseMock->shouldReceive('withInput')->andReturnSelf();

        $sessionMock = Mockery::mock(SessionInterface::class);
        $sessionMock->shouldReceive('get')
            ->with(Mockery::type('string'))
            ->andReturnUsing(function ($key) {
                if ($key === 'url.previous') {
                    return '/login';
                }
                return null; // For auth checks like 'login_web_...'
            });
        $sessionMock->shouldReceive('getFlashBag->set')->with('errors', Mockery::any());
        $sessionMock->shouldReceive('getFlashBag->set')->with('_old_input', Mockery::any());

        $this->app->instance(SessionInterface::class, $sessionMock);
        $this->app->instance('session', $sessionMock);

        $cookieManagerMock = Mockery::mock(CookieManager::class);
        // Add expectation for the "remember me" cookie check in SessionGuard
        $cookieManagerMock->shouldReceive('get')->andReturn(null);

        $cookieManagerMock->shouldReceive('addQueuedCookiesToResponse')
            ->once()
            ->andReturnUsing(function (RedirectResponse $response) {
                return $response->withHeader('Set-Cookie', 'bault_session=test_session_id; path=/');
            });
        $this->app->instance(CookieManager::class, $cookieManagerMock);

        $routerMock = Mockery::mock(Router::class);
        $this->app->instance(Router::class, $routerMock);

        $redirectorMock = Mockery::mock(Redirector::class);
        $redirectorMock->shouldReceive('back')->andReturn($redirectResponseMock);
        $redirectorMock->shouldReceive('getCookieManager')->andReturn($cookieManagerMock);
        $this->app->instance(Redirector::class, $redirectorMock);

        // Bind ValidatorFactory
        $this->app->singleton(ValidatorFactory::class, fn ($app) => new ValidatorFactory($app));
    }

    public function test_failed_validation_for_web_request_redirects_with_session_cookie_and_flashed_data(): void
    {
        // 3. Chuẩn bị Request không hợp lệ
        $invalidData = ['email' => 'not-an-email'];
        $request = (new ServerRequest('POST', '/login'))
            ->withParsedBody($invalidData)
            ->withHeader('Accept', 'text/html');

        $this->app->instance(\Psr\Http\Message\ServerRequestInterface::class, $request);

        $formRequest = $this->app->make(LoginRequest::class);

        try {
            $formRequest->validateResolved();

            $this->fail('HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();

            $this->assertInstanceOf(RedirectResponse::class, $response, "The response should be a RedirectResponse.");

            $this->assertTrue($response->hasHeader('Set-Cookie'), "The response should have a 'Set-Cookie' header.");
            $this->assertStringContainsString('bault_session', $response->getHeaderLine('Set-Cookie'), "The session cookie was not set in the response.");
        }
    }
}