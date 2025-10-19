<?php

namespace Core\WebSocket;

use Core\Application;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class WebSocketServer
{
    private Server $server;
    private LoggerInterface $logger;
    private Application $app;
    private WebSocketManager $manager;

    /**
     * Map user_id -> fd.
     * @var array<int|string, int>
     */
    private array $userConnections = [];

    public function __construct(Application $app, string $host, int $port)
    {
        $this->app = $app;
        $this->server = new Server($host, $port);
        $this->logger = $app->make(LoggerInterface::class);
        $this->manager = $app->make(WebSocketManager::class);

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
            $this->listenForMessages();
        });
    }

    public function onOpen(Server $server, Request $request): void
    {
        $fd = $request->fd;

        $tempId = 'debug-ws-' . $fd;
        $this->userConnections[$tempId] = $fd;
        $this->logger->info("WebSocket connection {$fd} accepted without authentication (temporary). Assigned ID: {$tempId}");
        $server->push($fd, json_encode([
            'status' => 'authenticated',
            'debug_session' => $tempId,
        ]));
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        try {
            $data = json_decode($frame->data, true);
            if (isset($data['type']) && $data['type'] === 'ping') {
                $server->push($frame->fd, json_encode(['type' => 'pong']));
                return;
            }
        } catch (\Throwable $e) {
        }
        $this->logger->info("Received message from {$frame->fd}: {$frame->data}. Ignoring non-ping message.");
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->logger->info("Connection close: {$fd}");

        $userId = array_search($fd, $this->userConnections, true);
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
            $this->logger->info("User {$userId} (fd: {$fd}) disconnected.");
        }
    }

    private function listenForMessages(): void
    {
        $channel = $this->manager->getChannel();
        if (!$channel) {
            $this->logger->error('WebSocketManager channel is not available. Real-time messaging will not work.');
            return;
        }

        $this->logger->info('WebSocket worker is listening for internal messages.');

        while (true) {
            $message = $channel->pop();
            if ($message === false) {
                continue;
            }

            if ($message['type'] === 'users' && isset($message['users'], $message['payload'])) {
                foreach ($message['users'] as $userId) {
                    $fd = $this->userConnections[(string)$userId] ?? null;
                    if ($fd && $this->server->isEstablished($fd)) {
                        $this->server->push($fd, json_encode($message['payload']));
                        $this->logger->info("Pushed message to user {$userId} (fd: {$fd})");
                    }
                }
            } elseif ($message['type'] === 'broadcast' && isset($message['payload'])) {
                $payload = json_encode($message['payload']);
                foreach ($this->server->connections as $fd) {
                    if ($this->server->isEstablished($fd)) {
                        $this->server->push($fd, $payload);
                    }
                }
                $this->logger->info('Broadcasted message to all clients.');
            }
        }
    }

    public function start(): void
    {
        $this->server->start();
    }
}
