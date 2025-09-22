<?php

namespace App\Http\Middleware;

use Closure;
use Core\Contracts\Http\Request;
use Core\Contracts\Http\Response;
use DebugBar\DebugBar;

class InjectDebugbarMiddleware
{
    public function __construct(protected DebugBar $debugbar)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Chỉ chèn debugbar vào các response HTML thành công
        if (
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 300 &&
            str_contains($response->getHeaderLine('Content-Type'), 'text/html')
        ) {
            $this->injectDebugbar($response);
        }

        return $response;
    }

    protected function injectDebugbar(Response $response): void
    {
        $content = $response->getBody()->getContents();
        $renderer = $this->debugbar->getJavascriptRenderer();

        // Lấy mã HTML/JS/CSS của debugbar
        $debugbarHead = $renderer->renderHead();
        $debugbarBody = $renderer->render();

        // Tìm vị trí thẻ đóng </head> và chèn mã vào trước nó
        $pos = strripos($content, '</head>');
        if ($pos !== false) {
            $content = substr_replace($content, $debugbarHead, $pos, 0);
        }

        // Tìm vị trí thẻ đóng </body> và chèn mã vào trước nó
        $pos = strripos($content, '</body>');
        if ($pos !== false) {
            $content = substr_replace($content, $debugbarBody, $pos, 0);
        } else {
            // Nếu không tìm thấy </body>, nối vào cuối cùng
            $content .= $debugbarBody;
        }

        // Tạo một stream mới với nội dung đã được cập nhật
        $body = $response->getBody();
        $body->rewind();
        $body->write($content);

        // Cập nhật lại header Content-Length
        $response = $response->withHeader('Content-Length', (string) $body->getSize());
    }
}
