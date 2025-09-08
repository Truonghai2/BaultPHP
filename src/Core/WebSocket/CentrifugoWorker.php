<?php

namespace Core\WebSocket;

use Core\Application;
use Core\Auth\TokenGuard;
use RoadRunner\Centrifugo\CentrifugoWorker as RoadRunnerCentrifugoWorker;
use RoadRunner\Centrifugo\Payload;
use RoadRunner\Centrifugo\Request;
use RoadRunner\Centrifugo\RequestFactory;
use Spiral\RoadRunner\Worker;
use Symfony\Component\Console\Output\OutputInterface;

class CentrifugoWorker
{
    private Application $app;
    private OutputInterface $output;
    private Worker $worker;

    public function __construct(Application $app, OutputInterface $output)
    {
        $this->app = $app;
        $this->output = $output;
        $this->worker = Worker::create();
    }

    public function run(): void
    {
        $requestFactory = new RequestFactory($this->worker);
        $centrifugoWorker = new RoadRunnerCentrifugoWorker($this->worker, $requestFactory);
        $this->output->writeln('<info>Centrifugo worker started. Waiting for proxy requests from Centrifugo server...</info>');

        while ($request = $centrifugoWorker->waitRequest()) {
            try {
                // Xử lý các loại request khác nhau
                match (get_class($request)) {
                    Connect::class => $this->handleConnect($request),
                    Refresh::class => $this->handleRefresh($request),
                    // Thêm các handler khác nếu cần: Publish, Subscribe, RPC...
                    default => $this->handleUnknown($request),
                };
            } catch (\Throwable $e) {
                $this->output->writeln("<error>Error processing Centrifugo request: {$e->getMessage()}</error>");
                $this->worker->error((string)$e);
                if (method_exists($request, 'error')) {
                    $request->error($e->getCode(), $e->getMessage());
                }
            }
        }
    }

    /**
     * Xử lý khi một client mới kết nối đến Centrifugo.
     * Đây là nơi để xác thực người dùng.
     */
    private function handleConnect(Connect $request): void
    {
        $token = $request->token;
        if (empty($token)) {
            $this->output->writeln('<comment>Connect request rejected: No token provided.</comment>');
            // Từ chối kết nối nếu không có token
            $request->disconnect('4001', 'Unauthorized: Token not provided');
            return;
        }

        try {
            /** @var TokenGuard $guard */
            $guard = $this->app->make('auth')->guard('centrifugo');
            $user = $guard->userFromToken($token);

            if (!$user) {
                $this->output->writeln('<comment>Connect request rejected: Invalid token.</comment>');
                $request->disconnect('4001', 'Unauthorized: Invalid token');
                return;
            }

            if (!in_array('websocket', $guard->getScopes())) {
                $this->output->writeln('<comment>Connect request rejected: Token missing websocket scope.</comment>');
                $request->disconnect('4003', 'Forbidden: Insufficient scope');
                return;
            }

            $this->output->writeln("<info>User authenticated via Centrifugo: {$user->id}</info>");

            $request->respond(new Payload\ConnectResponse(
                user: (string)$user->id,
            ));
        } catch (\Throwable $e) {
            $this->output->writeln("<error>Authentication error in CentrifugoWorker: {$e->getMessage()}</error>");
            $request->error(500, 'Internal Server Error');
        }
    }

    /**
     * Xử lý khi Centrifugo muốn làm mới session của client.
     */
    private function handleRefresh(Refresh $request): void
    {
        $this->output->writeln("<info>Refreshing session for user: {$request->user}</info>");

        $request->respond(new Payload\RefreshResponse(
            expired: false,
        ));
    }

    private function handleUnknown(object $request): void
    {
        $type = get_class($request);
        $this->output->writeln("<comment>Received unhandled request type: {$type}</comment>");

        if ($request instanceof Invalid) {
            $this->output->writeln("<error>Invalid request: {$request->getException()->getMessage()}</error>");
        }
    }
}
