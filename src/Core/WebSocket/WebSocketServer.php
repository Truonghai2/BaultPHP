<?php

namespace Core\WebSocket;

use Core\Application;
use Core\Auth\TokenGuard;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Throwable;

class WebSocketServer
{
    private Server $server;
    private LoggerInterface $logger;
    private TokenGuard $tokenGuard;
    private Application $app;

    /**
     * Bảng map từ user_id sang connection_id (fd).
     * Trong thực tế, nên dùng Redis để quản lý nếu có nhiều server.
     * @var array<int, int>
     */
    private array $userConnections = [];

    public function __construct(Application $app, string $host, int $port)
    {
        $this->app = $app;
        $this->server = new Server($host, $port);
        $this->logger = $app->make(LoggerInterface::class);
        $this->tokenGuard = $app->make('auth')->guard('token');

        $this->server->set([
            'worker_num' => 1,
        ]);

        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);
        $this->server->on('workerStart', [$this, 'onWorkerStart']);
    }

    public function onStart(Server $server): void
    {
        $this->logger->info("Swoole WebSocket Server is started at ws://{$server->host}:{$server->port}");
    }

    public function onWorkerStart(Server $server, int $workerId): void
    {
        go(function () {
            $this->listenForRedisMessages();
        });
    }

    public function onOpen(Server $server, Request $request): void
    {
        $this->logger->info("Connection open: {$request->fd}");
        // Client cần gửi token để xác thực ngay sau khi kết nối.
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        $this->logger->info("Received message from {$frame->fd}: {$frame->data}");
        $data = json_decode($frame->data, true);

        // Cơ chế xác thực: client gửi token qua message đầu tiên
        if (isset($data['type']) && $data['type'] === 'auth' && isset($data['token'])) {
            try {
                $user = $this->tokenGuard->userFromToken($data['token']);
                if ($user) {
                    $userId = $user->getAuthIdentifier();
                    $this->userConnections[$userId] = $frame->fd;
                    $this->logger->info("User {$userId} authenticated for connection {$frame->fd}");
                    $server->push($frame->fd, json_encode(['status' => 'authenticated']));
                } else {
                    $server->push($frame->fd, json_encode(['error' => 'invalid_token']));
                    $server->close($frame->fd);
                }
            } catch (Throwable $e) {
                $this->logger->error('Auth error: ' . $e->getMessage());
                $server->close($frame->fd);
            }
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->logger->info("Connection close: {$fd}");
        // Xóa mapping khi người dùng ngắt kết nối
        $userId = array_search($fd, $this->userConnections);
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
            $this->logger->info("User {$userId} disconnected.");
        }
    }

    private function listenForRedisMessages(): void
    {
        $redis = $this->app->make(\Core\Cache\CacheManager::class)->connection('redis')->client();
        $channel = 'websocket_messages';

        $this->logger->info("Listening for messages on Redis channel: {$channel}");

        $redis->subscribe([$channel], function ($redis, $chan, $msg) {
            if ($chan === 'websocket_messages') {
                $this->logger->info("Received from Redis: {$msg}");
                $data = json_decode($msg, true);

                if (isset($data['users'], $data['payload'])) {
                    foreach ($data['users'] as $userId) {
                        if (isset($this->userConnections[$userId])) {
                            $fd = $this->userConnections[$userId];
                            if ($this->server->isEstablished($fd)) {
                                $this->server->push($fd, json_encode($data['payload']));
                                $this->logger->info("Pushed message to user {$userId} (fd: {$fd})");
                            }
                        }
                    }
                }
            }
        });
    }

    public function start(): void
    {
        $this->server->start();
    }
}
