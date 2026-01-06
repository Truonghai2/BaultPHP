<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\Http\Kernel as HttpKernel;
use Core\Contracts\Session\SessionInterface;
use Core\Debug\DebugManager;
use Core\Exceptions\ServiceUnavailableException;
use Core\Foundation\StateResetter;
use Core\Tasking\CacheDebugDataTask;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Throwable;

/**
 * Encapsulates the entire lifecycle of a single HTTP request within the Swoole server.
 *
 * This class is responsible for transforming the request, passing it through the application kernel,
 * handling exceptions, sending the response, and cleaning up state. This separation of concerns
 * makes the SwooleServer class cleaner and the request handling logic more testable.
 */
final class RequestLifecycle
{
    private string $requestId;
    private float $startTime;
    private ?ResponseInterface $response = null;
    private ?DebugManager $debugManager = null;
    private ?float $endTime = null;

    public function __construct(
        private Application $app,
        private HttpKernel $kernel,
        private ExceptionHandler $exceptionHandler,
        private StateResetter $stateResetter,
        private SwoolePsr7Bridge $psr7Bridge,
        private bool $isDebug,
    ) {
        $this->startTime = microtime(true);
        $this->requestId = uniqid();
    }

    /**
     * The main entry point for handling a request.
     */
    public function handle(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            $this->initialize($swooleRequest);
            $psr7Request = $this->transformRequest($swooleRequest);
            $this->response = $this->executeKernel($psr7Request);
        } catch (Throwable $e) {
            $psr7Request = $this->app->bound(ServerRequestInterface::class)
                ? $this->app->make(ServerRequestInterface::class)
                : $this->psr7Bridge->toPsr7Request($swooleRequest);
            $this->response = $this->handleException($psr7Request, $e);
        } finally {
            $this->response = $this->finalizeResponse($this->response);
            $this->sendResponse($this->response, $swooleResponse);
            $this->terminate();
        }
    }

    /**
     * Sets up the application container for the incoming request.
     */
    private function initialize(SwooleRequest $swooleRequest): void
    {
        $this->app->instance('request_id', $this->requestId);
        $this->app->instance(SwooleRequest::class, $swooleRequest);

        if ($this->isDebug && $this->app->bound(DebugManager::class)) {
            $this->debugManager = $this->app->make(DebugManager::class);
            $this->debugManager->enable();
        }
    }

    /**
     * Transforms the Swoole request to a PSR-7 request and binds it.
     */
    private function transformRequest(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        $psr7Request = $this->psr7Bridge->toPsr7Request($swooleRequest);
        $this->app->instance(ServerRequestInterface::class, $psr7Request);
        return $psr7Request;
    }

    /**
     * Executes the application kernel and handles exceptions.
     */
    private function executeKernel(ServerRequestInterface $psr7Request): ResponseInterface
    {
        try {
            return $this->kernel->handle($psr7Request);
        } catch (Throwable $e) {
            return $this->handleException($psr7Request, $e);
        }
    }

    /**
     * Handles an exception by reporting it and rendering it to a response.
     */
    private function handleException(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        $this->exceptionHandler->report($request, $e);

        if ($e instanceof ServiceUnavailableException) {
            $this->getLogger()->warning("Request [{$this->requestId}]: Service unavailable, circuit breaker is likely open.", ['exception' => $e->getMessage()]);
        }

        return $this->exceptionHandler->render($request, $e);
    }

    /**
     * Adds final touches to the response before sending.
     */
    private function finalizeResponse(ResponseInterface $response): ResponseInterface
    {
        $response = $response->withHeader('X-Request-ID', $this->requestId);

        if ($this->isDebug) {
            $response = $response->withHeader('X-Debug-ID', $this->requestId);
        }

        return $response;
    }

    /**
     * Sends the final PSR-7 response to the client via the Swoole response object.
     */
    private function sendResponse(ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        if ($this->app->bound(ServerRequestInterface::class)) {
            $this->app->make(RequestLogger::class)->log(
                $this->app->make(ServerRequestInterface::class),
                $response,
                $this->startTime,
            );
        }

        $this->psr7Bridge->toSwooleResponse($response, $swooleResponse);
    }

    /**
     * Performs all post-request cleanup tasks.
     */
    private function terminate(): void
    {
        $this->endTime = microtime(true);

        if ($this->isDebug && $this->debugManager) {
            $this->handleDebugTermination();
        }

        // Call terminating callbacks (password rehashing, etc.)
        // These run AFTER response is sent but BEFORE cleanup
        $this->app->callTerminatingCallbacks();

        $this->stateResetter->reset();

        $this->app->forgetInstance(ServerRequestInterface::class);
        $this->app->forgetInstance(SwooleRequest::class);
        $this->app->forgetInstance('request_id');
    }

    /**
     * Gathers and dispatches debug information at the end of the request.
     */
    private function handleDebugTermination(): void
    {
        if ($this->debugManager->isEnabled()) {
            try {
                /** @var \Core\Contracts\Config\Repository $configService */
                $configService = $this->app->make('config');
                $this->debugManager->recordConfig($configService->all());

                if ($this->app->bound(ServerRequestInterface::class)) {
                    $request = $this->app->make(ServerRequestInterface::class);
                    $this->debugManager->set('cookies', $request->getCookieParams());
                }

                if ($this->app->bound(SessionInterface::class)) {
                    /** @var SessionInterface $session */
                    $session = $this->app->make(SessionInterface::class);
                    if ($session->isStarted()) {
                        $this->debugManager->set('session', $session->all());
                    }
                }

                if ($this->app->bound('debugbar')) {
                    /** @var \DebugBar\DebugBar $debugbar */
                    $debugbar = $this->app->make('debugbar');
                    $this->debugManager->setData($debugbar->collect()['__meta']);
                }

                if ($this->exceptionHandler->hasExceptions()) {
                    $this->debugManager->add('exceptions', $this->exceptionHandler->getExceptions());
                }

                $this->debugManager->recordRequestInfo([
                    'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                    'duration_ms' => round(($this->endTime - $this->startTime) * 1000, 2),
                ]);

                $debugData = $this->debugManager->getData();
                $expiration = config('debug.expiration', 3600);
                $task = new CacheDebugDataTask($this->requestId, $debugData, $expiration);

                /** @var SwooleServer $server */
                $server = $this->app->make(SwooleServer::class);
                $server->dispatchTask($task);

            } catch (Throwable $e) {
                $this->getLogger()->error('Failed to dispatch debug data caching task.', ['exception' => $e]);
            }
        }
    }

    private function getLogger(): LoggerInterface
    {
        return $this->app->make(LoggerInterface::class);
    }
}
