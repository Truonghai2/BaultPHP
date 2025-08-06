<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Command;
use Core\CQRS\CommandMiddleware;
use Psr\Log\LoggerInterface;
use Throwable;

class LoggingCommandMiddleware implements CommandMiddleware
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(Command $command, callable $next)
    {
        $commandClass = get_class($command);

        $this->logger->info("Executing command: [{$commandClass}]", [
            'command' => $this->serializeCommand($command),
        ]);

        try {
            $result = $next($command);

            $this->logger->info("Successfully executed command: [{$commandClass}]");

            return $result;
        } catch (Throwable $e) {
            $this->logger->error("Command execution failed: [{$commandClass}]", [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e; // Ném lại exception để các lớp xử lý lỗi khác có thể bắt được.
        }
    }

    private function serializeCommand(Command $command): array
    {
        return json_decode(json_encode($command), true);
    }
}
