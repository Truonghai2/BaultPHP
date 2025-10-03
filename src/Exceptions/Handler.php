<?php

namespace App\Exceptions;

use Core\Contracts\Exceptions\Handler as HandlerContract;
use Core\Contracts\View\Factory as ViewFactory;
use Core\Http\Redirector;
use Core\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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

        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        });
    }

    public function report(Throwable|Request|null $request, Throwable $e): void
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        $context = ['exception' => $e, 'status_code' => $this->getStatusCode($e)];
        if ($request instanceof Request) {
            $context['method'] = $request->getMethod();
            $context['uri'] = (string) $request->getUri();
            $context['ip'] = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        }
        $this->logger->error($e->getMessage(), $context);
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
        if ($e instanceof ValidationException && !$this->shouldReturnJson($request, $e)) {
            return $this->handleValidationException($request, $e);
        }

        if ($this->shouldReturnJson($request, $e)) {
            return $this->prepareJsonResponse($request, $e);
        }

        if (config('app.debug', false)) {
            return $this->renderExceptionForDebug($request, $e);
        }

        $statusCode = $this->getStatusCode($e);
        $viewPath = "errors.{$statusCode}";

        if ($this->view->exists($viewPath)) {
            return response($this->view->make($viewPath, ['exception' => $e])->render(), $statusCode);
        }

        $fallbackContent = $this->view->exists('errors.500') ? $this->view->make('errors.500', ['exception' => $e])->render() : 'Sorry, something went wrong.';
        return response($fallbackContent, $statusCode);
    }

    /**
     * Render một trang lỗi chi tiết cho môi trường debug.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Throwable $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function renderExceptionForDebug(Request $request, Throwable $e): ResponseInterface
    {
        $statusCode = $this->getStatusCode($e);
        $data = $this->getDebugData($request, $e);
        $content = view('errors.debug', $data);
        return response($content, $statusCode);
    }

    /**
     *
     *
     * @param Request $request
     * @param Throwable $e
     * @return array
     */
    protected function getDebugData(Request $request, Throwable $e): array
    {
        $file = $e instanceof \Core\View\ViewException ? $e->getViewPath() : $e->getFile();
        $line = $e instanceof \Core\View\ViewException ? $e->getViewLine() : $e->getLine();
        return [
            'exception' => $e,
            'request' => [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'headers' => $request->getHeaders(),
                'query' => $request->getQueryParams(),
                'body' => $request->getParsedBody(),
            ],
            'codeSnippet' => $this->getCodeSnippet($file, $line),
        ];
    }

    /**
     * Lấy một đoạn code từ file nơi lỗi xảy ra.
     *
     * @param string $path Đường dẫn đến file
     * @param int $errorLine Dòng bị lỗi
     * @param int $contextLines Số dòng ngữ cảnh xung quanh dòng lỗi
     * @return array|null
     */
    protected function getCodeSnippet(string $path, int $errorLine, int $contextLines = 10): ?array
    {
        if (!is_readable($path)) {
            return null;
        }

        // Tối ưu hóa: Thay vì đọc toàn bộ file bằng file(), chúng ta chỉ đọc các dòng cần thiết.
        // Điều này ngăn ngừa lỗi hết bộ nhớ khi file gây lỗi rất lớn.
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return null;
        }

        $startLine = max(1, $errorLine - $contextLines);
        $endLine = $errorLine + $contextLines;
        $currentLine = 0;
        $snippet = [];

        while (($lineContent = fgets($handle)) !== false) {
            $currentLine++;
            if ($currentLine >= $startLine && $currentLine <= $endLine) {
                $snippet[] = [
                    'number' => $currentLine,
                    'content' => htmlspecialchars(rtrim($lineContent, "\r\n"), ENT_QUOTES, 'UTF-8'),
                ];
            }
            if ($currentLine > $endLine) {
                break;
            }
        }

        fclose($handle);
        return $snippet;
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

    /**
     * Handle ValidationException for non-JSON requests by redirecting back with errors.
     */
    protected function handleValidationException(Request $request, ValidationException $e): ResponseInterface
    {
        /** @var Redirector $redirector */
        $redirector = app(Redirector::class);

        return $redirector->back()
            ->withInput($request->getParsedBody() ?? [])
            ->withErrors($e->errors());
    }

    /**
     * Render an exception for the console.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Throwable $e
     */
    public function renderForConsole(OutputInterface $output, Throwable $e): void
    {
        $output->writeln('<error>[' . OutputFormatter::escape(get_class($e)) . ']</error>');
        $output->writeln('<error>Message: ' . OutputFormatter::escape($e->getMessage()) . '</error>');
        $output->writeln('<comment>In ' . OutputFormatter::escape($e->getFile()) . ' on line ' . $e->getLine() . '</comment>');
        $output->writeln('');
        $output->writeln('<comment>Stack trace:</comment>');
        $output->writeln(OutputFormatter::escape($e->getTraceAsString()));
    }
}
