<?php

namespace App\Exceptions;

use Core\Exceptions\ValidationException;
use Http\Request;
use Http\Response;
use Throwable;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class Handler
{
    public function report(Throwable $e): void
    {
        error_log($e);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function renderForHttp(Request $request, Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return (new Response())->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->getCode());
        }

        if ($e instanceof AuthorizationException) {
            return (new Response())->json(['message' => $e->getMessage()], $e->getCode());
        }

        if (env('APP_DEBUG', false)) {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
            $content = $whoops->handleException($e);
            return (new Response())->setContent($content)->setStatus(500);
        }

        if ($request->isJson() || $request->isAjax()) {
            return (new Response())->json(['message' => 'Server Error'], 500);
        }

        return (new Response())->setContent('<h1>500 Server Error</h1><p>Đã xảy ra lỗi. Vui lòng thử lại sau.</p>')->setStatus(500);
    }

    public function render(Throwable $e): void
    {
        if (env('APP_DEBUG', false)) {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
            $whoops->handleException($e);
        } else {
            http_response_code(500);
            echo "Đã xảy ra lỗi. Vui lòng thử lại sau.";
        }
    }

    public function handle(Throwable $e): void
    {
        $this->report($e);
        $this->render($e);
    }
}
