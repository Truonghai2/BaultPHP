<?php

namespace App\Exceptions;

use Core\Exceptions\ValidationException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as WhoopsRun;

class Handler
{
    protected LoggerInterface $logger;

    protected array $dontReport = [
        AuthorizationException::class,
        NotFoundException::class,
        ValidationException::class,
        TokenMismatchException::class,
        MethodNotAllowedException::class,
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function report(Throwable $e): void
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        $this->logger->error($e->getMessage(), [
            'exception' => $e,
        ]);
    }

    protected function shouldntReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        return false;
    }

    public function handle(Throwable $e): void
    {
        try {
            $this->report($e);

            // For CLI, use the plain text handler and let it exit
            if (php_sapi_name() === 'cli') {
                (new WhoopsRun())->pushHandler(new \Whoops\Handler\PlainTextHandler())->handleException($e);
                return;
            }

            // For HTTP requests, render and send the response.
            // This is the last line of defense. The script will terminate after this handler.
            /** @var \Core\Application $app */
            $app = app(); // Assuming a global app() helper
            $request = $app->isBound(Request::class) ? $app->make(Request::class) : null;

            if (!$request) {
                 http_response_code(500);
                 echo "A critical error occurred during application bootstrap.";
                 return;
            }

            $response = $this->renderForHttp($request, $e);

            (new \Http\ResponseEmitter())->emit($response);
        } catch (Throwable $fatal) {
            // If the handler itself fails, output a plain error to avoid a blank page.
            http_response_code(500);
            $message = config('app.debug', false)
                ? sprintf("Fatal error in exception handler: %s in %s:%d", $fatal->getMessage(), $fatal->getFile(), $fatal->getLine())
                : 'A critical server error occurred.';
            echo $message;
        }
    }

    public function renderForHttp(Request $request, Throwable $e): ResponseInterface
    {
        if ($this->shouldReturnJson($request, $e)) {
            return $this->prepareJsonResponse($request, $e);
        }

        // Nếu đang ở chế độ debug, luôn hiển thị trang lỗi chi tiết của Whoops.
        if (config('app.debug', false)) {
            return $this->renderExceptionWithWhoops($e);
        }

        // Nếu không ở chế độ debug, render các trang lỗi tùy chỉnh.
        $statusCode = $this->getStatusCode($e);
        $viewPath = "errors.{$statusCode}";

        // Kiểm tra xem có view cho mã lỗi cụ thể không (ví dụ: errors.404).
        if (view()->exists($viewPath)) {
            return \response(\view($viewPath, ['exception' => $e]), $statusCode);
        }

        // Nếu không, trả về một trang lỗi 500 chung nếu có, hoặc một chuỗi mặc định.
        $fallbackContent = view()->exists('errors.500') ? \view('errors.500', ['exception' => $e]) : 'Sorry, something went wrong.';
        return response($fallbackContent, $statusCode);
    }

    protected function shouldReturnJson(Request $request, Throwable $e): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest' || 
               str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json') || 
               str_starts_with($request->getUri()->getPath(), '/api/');
    }

    protected function prepareJsonResponse(Request $request, Throwable $e): ResponseInterface
    {
        $statusCode = $this->getStatusCode($e);

        $response = [
            'message' => $e->getMessage() ?: 'Server Error',
        ];

        if ($e instanceof ValidationException) {
            $response['errors'] = $e->errors();
            $response['message'] = 'The given data was invalid.';
        }

        if (config('app.debug', false)) {
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = explode("\n", $e->getTraceAsString());
        }

        return response()->json($response, $statusCode);
    }

    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return (int) $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && is_int($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600 && $e->getCode() !== 0) {
            return $e->getCode();
        }

        $map = [
            ValidationException::class => 422,
            AuthorizationException::class => 403,
            NotFoundException::class => 404,
            MethodNotAllowedException::class => 405,
            TokenMismatchException::class => 419,
        ];

        foreach ($map as $class => $code) {
            if ($e instanceof $class) {
                return $code;
            }
        }

        return 500;
    }

    protected function renderExceptionWithWhoops(Throwable $e): ResponseInterface
    {
        $whoops = new WhoopsRun();

        // Prevent Whoops from sending output and exiting directly.
        // This allows us to capture the output and wrap it in a proper PSR-7 response.
        $whoops->writeToOutput(false);
        $whoops->allowQuit(false);

        $whoops->pushHandler(new PrettyPageHandler());
        $html = $whoops->handleException($e);

        return response($html, $this->getStatusCode($e));
    }
}