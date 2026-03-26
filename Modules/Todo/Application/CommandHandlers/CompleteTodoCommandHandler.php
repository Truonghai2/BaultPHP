<?php

namespace Modules\Todo\Application\CommandHandlers;

use Core\CQRS\{Command, CommandHandler};
use Core\Support\Result;
use Core\Events\Dispatcher as EventDispatcher;
use Modules\Todo\Infrastructure\Repositories\TodoWriteRepository;

class CompleteTodoCommandHandler implements CommandHandler
{
    public function __construct(
        private TodoWriteRepository $writeRepo,
        private EventDispatcher $eventDispatcher
    ) {}

    public function handle(Command $command): Result
    {
        // 1. Load aggregate from write store
        $todoResult = $this->writeRepo->findById($command->todoId);
        if ($todoResult->isFailure()) {
            return Result::fail('Todo not found');
        }

        $todo = $todoResult->getValue();

        // 2. Execute domain logic
        try {
            $todo->complete();
        } catch (\DomainException $e) {
            return Result::fail($e->getMessage());
        }

        // 3. Persist changes
        $saveResult = $this->writeRepo->save($todo);
        if ($saveResult->isFailure()) {
            return $saveResult;
        }

        // 4. Publish domain events
        foreach ($todo->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event->eventName(), $event);
        }

        return Result::ok();
    }

    public function getCommandClass(): string
    {
        return CompleteTodoCommand::class;
    }

    public function getBoundedContext(): string
    {
        return 'Todo';
    }
}
