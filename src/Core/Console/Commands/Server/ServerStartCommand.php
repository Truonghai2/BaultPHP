<?php

namespace Core\Console\Commands\Server;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Http\Kernel as HttpKernelContract;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use RazonYang\Psr7\Swoole\ServerRequestFactory;
use RazonYang\Psr7\Swoole\EmitterFactory;

class ServerStartCommand extends BaseCommand
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    public function signature(): string
    {
        return 'server:start {--host= : The host to bind the server to} {--port= : The port to bind the server to}';
    }

    public function description(): string
    {
        return 'Starts the Swoole HTTP server.';
    }

    /**
     * Overriding execute for better access to Input/Output and consistency.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!extension_loaded('swoole')) {
            $this->error('The Swoole extension is not installed or enabled. This command cannot function without it.');
            return self::FAILURE;
        }

        // Prioritize command-line options, then config, then default.
        $host = $input->getOption('host') ?? config('server.swoole.host', '127.0.0.1');
        $port = (int) ($input->getOption('port') ?? config('server.swoole.port', 9501));

        $pidFile = config('server.swoole.pid_file');
        $daemonize = config('server.swoole.daemonize', false);

        if ($pidFile && file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid && posix_kill($pid, 0)) {
                $this->error("Swoole server is already running on {$host}:{$port} with PID {$pid}.");
                return self::FAILURE;
            } else {
                unlink($pidFile); // Clean up stale PID file
            }
        }

        $this->info("Starting Swoole HTTP server on {$host}:{$port}...");

        $server = new Server($host, $port);

        $server->set([
            'worker_num' => config('server.swoole.worker_num', swoole_cpu_num() * 2),
            'daemonize' => $daemonize,
            'pid_file' => $pidFile,
            'log_file' => config('server.swoole.log_file'),
            'enable_static_handler' => true,
            'document_root' => public_path(),
        ]);

        $server->on('start', function (Server $server) use ($host, $port, $daemonize) {
            $this->info("Swoole HTTP server started on http://{$host}:{$port}");
            if ($daemonize) {
                $this->comment("Server is running in daemon mode. PID: {$server->master_pid}");
            } else {
                $this->comment('Press Ctrl+C to stop the server.');
            }
        });

        $server->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) {
            // This is the bridge between Swoole and the BaultFrame framework.
            // It ensures every request is handled by the Http Kernel.
            $kernel = $this->app->make(HttpKernelContract::class);

            // 1. Convert the Swoole request to a PSR-7 compliant request.
            $psrRequest = (new ServerRequestFactory())->create($swooleRequest);

            // 2. Handle the request within the framework's Kernel.
            $psrResponse = $kernel->handle($psrRequest);

            // 3. Convert the PSR-7 response back to a Swoole response and send it.
            // The second argument to emit() is `$withoutBody`. We should not send a body
            // for responses with status codes 204, 205, and 304 as per HTTP specification.
            // The previous logic was inverted and only checked for 204.
            $withoutBody = in_array($psrResponse->getStatusCode(), [204, 205, 304], true);
            (new EmitterFactory())->create($swooleResponse)->emit($psrResponse, $withoutBody);

            if (method_exists($kernel, 'terminate')) {
                $kernel->terminate($psrRequest, $psrResponse);
            }
        });

        $server->start();

        return self::SUCCESS;
    }

    public function handle(): int
    {
        // This is required by the abstract class but we use execute() instead.
        return $this->execute($this->input, $this->output);
    }
}