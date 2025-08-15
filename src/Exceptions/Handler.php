<?php

namespace App\Exceptions;

use Core\Contracts\Exceptions\Handler as HandlerContract;
use Core\Contracts\View\Factory as ViewFactory;
use Core\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Throwable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as WhoopsRun;

class Handler implements HandlerContract
{
    protected array $dontReport = [
        AuthorizationException::class,
        NotFoundException::class,
        ValidationException::class,
        TokenMismatchException::class,
        MethodNotAllowedException::class,
    ];

    public function __construct(
        protected LoggerInterface $logger,
        protected ViewFactory $view,
    ) {
    }

    /**
     * Bootstrap the exception handler.
     * This converts PHP errors into exceptions and registers the handler.
     */
    public function bootstrap(): void
    {
        error_reporting(-1);

        // Convert all errors to ErrorException
        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        });
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

    /**
     * Render an exception into an HTTP response.
     */
    public function render(Request $request, Throwable $e): ResponseInterface
    {
        if ($this->shouldReturnJson($request, $e)) {
            return $this->prepareJsonResponse($request, $e);
        }

        // Nếu đang ở chế độ debug, luôn hiển thị trang lỗi chi tiết của Whoops.
        if (config('app.debug', false)) {
            return $this->renderExceptionWithWhoops($request, $e);
        }

        // Nếu không ở chế độ debug, render các trang lỗi tùy chỉnh.
        $statusCode = $this->getStatusCode($e);
        $viewPath = "errors.{$statusCode}";

        // Kiểm tra xem có view cho mã lỗi cụ thể không (ví dụ: errors.404).
        if ($this->view->exists($viewPath)) {
            return response($this->view->make($viewPath, ['exception' => $e])->render(), $statusCode);
        }

        // Nếu không, trả về một trang lỗi 500 chung nếu có, hoặc một chuỗi mặc định.
        $fallbackContent = $this->view->exists('errors.500') ? $this->view->make('errors.500', ['exception' => $e])->render() : 'Sorry, something went wrong.';
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

    protected function renderExceptionWithWhoops(Request $request, Throwable $e): ResponseInterface
    {
        $whoops = new WhoopsRun();

        // Prevent Whoops from sending output and exiting directly.
        // This allows us to capture the output and wrap it in a proper PSR-7 response.
        $whoops->writeToOutput(false);
        $whoops->allowQuit(false);

        if ($this->shouldReturnJson($request, $e)) {
            $whoops->pushHandler(new JsonResponseHandler());
        } else {
            $prettyPageHandler = new PrettyPageHandler();

            // Cho phép mở file trực tiếp từ trang lỗi Whoops vào editor của bạn.
            // Thêm các bảng dữ liệu tùy chỉnh để cung cấp thêm ngữ cảnh gỡ lỗi.
            $this->addDebugInfoToHandler($prettyPageHandler, $request);

            // Cấu hình editor để mở file trực tiếp từ trang lỗi.
            // Ví dụ: EDITOR=vscode trong file .env
            // Bạn có thể cấu hình editor trong file .env (ví dụ: EDITOR=vscode)
            // và nó sẽ được đọc thông qua config('app.editor').
            // Các giá trị hợp lệ: "phpstorm", "vscode", "sublime", "atom", "emacs", "textmate", "macvim"
            if ($editor = config('app.editor')) {
                $prettyPageHandler->setEditor($editor);
            }
            $whoops->pushHandler($prettyPageHandler);
        }

        $html = $whoops->handleException($e);

        return response($html, $this->getStatusCode($e));
    }

    public function renderForConsole($output, Throwable $e): void
    {
        (new WhoopsRun())->pushHandler(new \Whoops\Handler\PlainTextHandler())->handleException($e);
    }

    /**
     * Add custom debug information to the Whoops page handler.
     *
     * @param \Whoops\Handler\PrettyPageHandler $handler
     * @param \Psr\Http\Message\ServerRequestInterface $request
     */
    protected function addDebugInfoToHandler(PrettyPageHandler $handler, Request $request): void
    {
        // Thêm thông tin chi tiết về Request
        $handler->addDataTable('Request Info', [
            'URI' => $request->getUri()->__toString(),
            'Method' => $request->getMethod(),
            'Headers' => $request->getHeaders(),
            'Server Params' => $request->getServerParams(),
            'Body' => (string) $request->getBody(),
        ]);

        // Thêm thông tin về Session
        try {
            if (app()->has('session') && app('session')->isStarted()) {
                $handler->addDataTable('Session Data', app('session')->all());
            }
        } catch (Throwable) {
            // Bỏ qua nếu service session không được cấu hình hoặc có lỗi
        }

        // Thêm thông tin về người dùng đã xác thực
        try {
            if (app()->has(\Core\Auth\AuthManager::class)) {
                /** @var \Core\Auth\AuthManager $auth */
                $auth = app(\Core\Auth\AuthManager::class);
                if ($auth->check()) {
                    $user = $auth->user();
                    $handler->addDataTable('Authenticated User', [
                        'ID' => $user->getAuthIdentifier(),
                        'Class' => get_class($user),
                        'Attributes' => method_exists($user, 'toArray') ? $user->toArray() : 'N/A',
                    ]);
                }
            }
        } catch (Throwable) {
            // Bỏ qua nếu service auth không được cấu hình hoặc có lỗi
        }
    }
}
