<?php

namespace Core\WebSocket;

use Core\Application;
use Core\Auth\AuthManager;
use Core\Cache\CacheManager;
use Predis\ClientInterface as RedisClient;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Throwable;

class WebSocketServer
{
    private Server $server;
    private LoggerInterface $logger;
    private Application $app;
    private AuthManager $auth;
    private RedisClient $redis;

    /**
     * Key prefix trong Redis để lưu map user_id -> fd.
     */
    private const REDIS_USER_CONNECTION_KEY = 'ws:user_connections';

    public function __construct(Application $app, string $host, int $port)
    {
        $this->app = $app;
        $this->server = new Server($host, $port);
        $this->logger = $app->make(LoggerInterface::class);
        $this->auth = $app->make('auth');
        $this->redis = $app->make(CacheManager::class)->connection('redis')->client();

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
        $token = $request->get['token'] ?? null;
        $fd = $request->fd;

        if (!$token) {
            $this->logger->warning("Connection {$fd} rejected: No token provided.");
            $server->push($fd, json_encode(['error' => 'unauthorized', 'message' => 'Token not provided.']));
            $server->close($fd);
            return;
        }

        try {
            $guard = $this->auth->guard('centrifugo');
            $user = $guard->userFromToken($token);

            if ($user && in_array('websocket', $guard->getScopes())) {
                $userId = $user->getAuthIdentifier();
                $this->redis->hset(self::REDIS_USER_CONNECTION_KEY, (string)$userId, $fd);
                $this->logger->info("User {$userId} authenticated and connected with fd {$fd}.");
                $server->push($fd, json_encode(['status' => 'authenticated', 'user_id' => $userId]));
            } else {
                $this->logger->warning("Connection {$fd} rejected: Invalid token or insufficient scope.");
                $server->push($fd, json_encode(['error' => 'unauthorized', 'message' => 'Invalid token or scope.']));
                $server->close($fd);
            }
        } catch (Throwable $e) {
            $this->logger->error("Auth error on connection {$fd}: " . $e->getMessage());
            $server->close($fd);
        }
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        $this->logger->info("Received message from {$frame->fd}: {$frame->data}. Ignoring.");
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->logger->info("Connection close: {$fd}");

        $allConnections = $this->redis->hgetall(self::REDIS_USER_CONNECTION_KEY);
        foreach ($allConnections as $userId => $connectionFd) {
            if ((int)$connectionFd === $fd) {
                $this->redis->hdel(self::REDIS_USER_CONNECTION_KEY, [$userId]);
                $this->logger->info("User {$userId} (fd: {$fd}) disconnected and removed from Redis.");
                break;
            }
        }
    }

    private function listenForRedisMessages(): void
    {
        $channel = 'websocket_messages';

        $this->logger->info("Listening for messages on Redis channel: {$channel}");

        $subRedis = $this->app->make(CacheManager::class)->connection('redis')->client();

        $subRedis->subscribe([$channel], function ($redis, $chan, $msg) {
            if ($chan === 'websocket_messages') {
                $this->logger->info("Received from Redis: {$msg}");
                $data = json_decode($msg, true);

                if (isset($data['users'], $data['payload'])) {
                    foreach ($data['users'] as $userId) {
                        $fd = $this->redis->hget(self::REDIS_USER_CONNECTION_KEY, (string)$userId);
                        if ($fd) {
                            $fd = (int)$fd;
                            if ($this->server->isEstablished($fd)) {
                                $this->server->push($fd, json_encode($data['payload']));
                                $this->logger->info("Pushed message to user {$userId} (fd: {$fd})");
                            } else {
                                $this->redis->hdel(self::REDIS_USER_CONNECTION_KEY, [(string)$userId]);
                                $this->logger->warning("Connection for user {$userId} (fd: {$fd}) not established. Removed from Redis.");
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
