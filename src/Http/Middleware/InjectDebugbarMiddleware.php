<?php

namespace App\Http\Middleware;

use DebugBar\DebugBar;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InjectDebugbarMiddleware implements MiddlewareInterface
{
    public function __construct(protected DebugBar $debugbar)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $handler->handle($request);

        // Chỉ inject debugbar cho response HTML và không phải là request SPA
        if (
            $response->getStatusCode() >= 200 &&
            $response->getStatusCode() < 300 &&
            str_contains($response->getHeaderLine('Content-Type'), 'text/html') &&
            !$request->hasHeader('X-SPA-NAVIGATE')
        ) {
            $this->injectDebugbar($response);
        }

        return $response;
    }

    protected function injectDebugbar(ResponseInterface &$response): void
    {
        $body = $response->getBody();
        $body->rewind(); // Đảm bảo đọc từ đầu stream
        $content = $body->getContents();

        if (empty($content)) {
            return;
        }

        $this->debugbar->collect();

        $renderer = $this->debugbar->getJavascriptRenderer();

        $debugbarHead = $renderer->renderHead();
        $debugbarBody = $renderer->render();

        $requestId = app('request_id');
        $user = auth()->user();

        if ($requestId && auth()->check()) {
            try {
                session()->set('debug_session_id', $requestId);
                $realtimeScript = $this->getRealtimeJavascript();
                $debugbarBody .= $realtimeScript;
            } catch (\Throwable $e) {
                error_log('Debugbar real-time error: ' . $e->getMessage());
            }
        }

        $content = $this->injectContent($content, $debugbarHead, $debugbarBody);

        $newBody = Stream::create($content);
        $response = $response->withBody($newBody);

        if ($response->hasHeader('Content-Length')) {
            $response = $response->withHeader('Content-Length', (string) $newBody->getSize());
        }
    }

    /**
     * Inject debugbar content vào HTML
     */
    protected function injectContent(string $content, string $headContent, string $bodyContent): string
    {
        // Inject head content
        $headPos = strripos($content, '</head>');
        if ($headPos !== false) {
            $content = substr_replace($content, $headContent, $headPos, 0);
        }

        // Inject body content
        $bodyPos = strripos($content, '</body>');
        if ($bodyPos !== false) {
            $content = substr_replace($content, $bodyContent, $bodyPos, 0);
        } else {
            // Fallback: append to end if </body> not found
            $content .= $bodyContent;
        }

        return $content;
    }

    /**
     * Generate real-time debugging JavaScript
     */
    protected function getRealtimeJavascript(): string
    {
        $wsUrl = $this->getWebSocketUrl();

        return <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof phpdebugbar === 'undefined') {
        console.warn('DebugBar not available for real-time debugging.');
        return;
    }
 
    const connect = () => {
        // Không cần token nữa, trình duyệt sẽ tự động gửi cookie session
        // để xác thực kết nối WebSocket.
        const ws = new WebSocket('$wsUrl');
 
        ws.onopen = () => {
            console.log('DebugBar WebSocket connection established.');
        };

        ws.onmessage = (event) => {
            handleMessage(JSON.parse(event.data));
        };
 
        ws.onclose = (event) => {
            console.log('DebugBar WebSocket connection closed. Reconnecting in 5 seconds...', event.reason);
            setTimeout(connect, 5000);
        };
    };
 
    const handleMessage = (message) => {
        const payload = message.payload; // Dữ liệu thực tế nằm trong payload
        if (!payload?.type || !phpdebugbar.ajaxHandler) return;

        try {
            switch(payload.type) {
                case 'query':
                    handleQueryDebug(payload);
                    break;
                case 'log':
                    handleLogDebug(payload);
                    break;
                case 'event':
                    handleEventDebug(payload);
                    break;
                case 'cache':
                    handleCacheDebug(payload);
                    break;
            }
        } catch (error) {
            console.error('Debug handler error:', error);
        }
    };
 
    function handleQueryDebug(payload) {
        const pdo = phpdebugbar.ajaxHandler.collectors.pdo;
        if (!pdo || !payload.data) return;

        const statement = {
            sql: payload.data.sql,
            row_count: payload.data.row_count,
            duration_str: (payload.data.duration * 1000).toFixed(2) + 'ms',
            memory_str: (payload.data.memory / 1024).toFixed(2) + 'KB',
            is_success: true,
            params: payload.data.params || [],
            source: payload.data.source || null
        };
        pdo.addQuery(statement);
    }
 
    function handleLogDebug(payload) {
        const messages = phpdebugbar.ajaxHandler.collectors.messages;
        if (messages && payload.data?.message) {
            messages.addMessage(payload.data.message, payload.data.level || 'info');
        }
    }
 
    function handleEventDebug(payload) {
        const events = phpdebugbar.ajaxHandler.collectors.events;
        if (events && payload.data?.name) {
            events.addMessage({
                message: payload.data.name,
                is_string: false,
                data: payload.data.payload || {}, // Sửa lại để lấy payload từ data
                label: 'event',
                time: Date.now() / 1000
            });
        }
    }

    function handleCacheDebug(payload) {
        const cache = phpdebugbar.ajaxHandler.collectors.cache;
        if (cache && payload.data?.type && payload.data?.key) {
            const message = `Cache \${payload.data.type} for key '\${payload.data.key}' on store '\${payload.data.store || 'default'}'.`; // Sửa template string
            cache.addMessage({
                label: payload.data.type,
                message: message,
                is_string: true,
                time: Date.now() / 1000
            });
        }
    }
 
    connect();
});
</script>
JS;
    }

    /**
     * Get WebSocket URL from app config.
     */
    protected function getWebSocketUrl(): string
    {
        $request = app('request');
        $host = $request->getUri()->getHost();
        $port = config('app.websocket_port', 9502);

        return "ws://{$host}:{$port}";
    }
}
