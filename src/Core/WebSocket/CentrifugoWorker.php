<?php

namespace Core\WebSocket;

use Core\Application;
use Core\Support\Facades\Auth;
use RoadRunner\Centrifugo\CentrifugoWorker as RoadRunnerCentrifugoWorker;
use RoadRunner\Centrifugo\Payload;
use RoadRunner\Centrifugo\Request;
use RoadRunner\Centrifugo\Request\RequestFactory;
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
                    Request\Connect::class => $this->handleConnect($request),
                    Request\Refresh::class => $this->handleRefresh($request),
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
    private function handleConnect(Request\Connect $request): void
    {
        $token = $request->token;
        if (empty($token)) {
            $this->output->writeln('<comment>Connect request rejected: No token provided.</comment>');
            // Từ chối kết nối nếu không có token
            $request->disconnect('4001', 'Unauthorized: Token not provided');
            return;
        }

        try {
            // Sử dụng Auth guard của bạn để xác thực token
            $user = Auth::guard('jwt_ws')->userFromToken($token);

            if (!$user) {
                $this->output->writeln('<comment>Connect request rejected: Invalid token.</comment>');
                $request->disconnect('4001', 'Unauthorized: Invalid token');
                return;
            }

            $this->output->writeln("<info>User authenticated via Centrifugo: {$user->id}</info>");

            // Cho phép kết nối và trả về user ID cho Centrifugo
            // Centrifugo sẽ gắn user ID này vào connection context.
            $request->respond(new Payload\ConnectResponse(
                user: (string)$user->id,
            ));
        } catch (\Throwable $e) {
            $this->output->writeln("<error>Authentication error: {$e->getMessage()}</error>");
            $request->error(500, 'Internal Server Error');
        }
    }

    /**
     * Xử lý khi Centrifugo muốn làm mới session của client.
     */
    private function handleRefresh(Request\Refresh $request): void
    {
        // Logic ở đây tương tự như connect, bạn có thể cấp lại token mới nếu cần
        // hoặc chỉ đơn giản là cho phép gia hạn session.
        $this->output->writeln("<info>Refreshing session for user: {$request->user}</info>");

        $request->respond(new Payload\RefreshResponse(
            expired: false, // true nếu muốn ngắt kết nối
        ));
    }

    private function handleUnknown(object $request): void
    {
        $type = get_class($request);
        $this->output->writeln("<comment>Received unhandled request type: {$type}</comment>");

        if ($request instanceof Request\Invalid) {
            $this->output->writeln("<error>Invalid request: {$request->getException()->getMessage()}</error>");
        }
    }
}
