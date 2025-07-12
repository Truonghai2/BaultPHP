<?php

namespace App\Exceptions;

use Throwable;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class Handler
{
    public function report(Throwable $e): void
    {
        error_log($e);
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
