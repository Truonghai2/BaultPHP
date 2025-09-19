<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Command;
use Core\CQRS\CommandMiddleware;
use Core\Server\SwooleServer;
use Core\Tasking\LogCommandTask;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * LoggingCommandMiddleware is responsible for logging the execution of commands.
 * It logs the command details before and after execution, as well as any exceptions that occur.
 */
class LoggingCommandMiddleware implements CommandMiddleware
{
    /**
     * Logger instance for logging command execution details.
     *
     * @var LoggerInterface
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SwooleServer $server,
    ) {
    }

    /**
     * Handle the command execution and log the command details.
     *
     * @param Command $command
     * @param callable $next
     * @return mixed
     * @throws Throwable
     */
    public function handle(Command $command, callable $next)
    {
        $commandClass = get_class($command);

        $this->dispatchLog('info', "Executing command: [{$commandClass}]", [
            'command' => $this->serializeCommand($command),
        ]);

        try {
            $result = $next($command);

            $this->dispatchLog('info', "Successfully executed command: [{$commandClass}]");

            return $result;
        } catch (Throwable $e) {
            $this->dispatchLog('error', "Command execution failed: [{$commandClass}]", [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Serialize the command to an array for logging.
     *
     * @param Command $command
     * @return array
     */
    private function serializeCommand(Command $command): array
    {
        return json_decode(json_encode($command), true);
    }

    /**
     * Dispatches a log message to a Swoole task worker.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    private function dispatchLog(string $level, string $message, array $context = []): void
    {
        $task = new LogCommandTask($level, $message, $context);
        $this->server->dispatchTask($task);
    }
}
