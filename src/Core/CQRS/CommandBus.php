<?php 

namespace Core\CQRS;

use Illuminate\Bus\Dispatcher;

class CommandBus
{
    protected array $handlers = [];

    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(object $command): mixed
    {
        $class = get_class($command);
        if (!isset($this->handlers[$class])) {
            throw new \RuntimeException("No handler for command: {$class}");
        }
        return call_user_func($this->handlers[$class], $command);
    }
}