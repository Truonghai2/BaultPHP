<?php

namespace Modules\Todo\Application\CommandHandlers;

use Core\CQRS\{Command, CommandHandler};
use Core\Support\Result;
use Core\Events\Dispatcher as EventDispatcher;
use Modules\Todo\Domain\Entities\Todo;
use Modules\Todo\Domain\ValueObjects\TodoTitle;
use Modules\Todo\Infrastructure\Repositories\TodoWriteRepository;
use Ramsey\Uuid\Uuid;

/**
 * Handler: Create Todo Command.
 * 
 * Orchestrates the creation of a new Todo following CQRS pattern.
 */
class CreateTodoCommandHandler implements CommandHandler
{
    public function __construct(
        private TodoWriteRepository $writeRepo,
        private EventDispatcher $eventDispatcher
    ) {}

    public function handle(Command $command): Result
    {
        // 1. Validate title
        $titleResult = TodoTitle::create($command->title);
        if ($titleResult->isFailure()) {
            return $titleResult;
        }

        // 2. Create entity (domain logic)
        $todo = Todo::create(
            id: Uuid::uuid4()->toString(),
            title: $titleResult->getValue(),
            userId: $command->userId
        );

        // 3. Persist to write store
        $saveResult = $this->writeRepo->save($todo);
        if ($saveResult->isFailure()) {
            return $saveResult;
        }

        // 4. Publish domain events
        foreach ($todo->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event->eventName(), $event);
        }

        return Result::ok([
            'todo_id' => $todo->id(),
            'title' => $todo->title()->value(),
        ]);
    }

    public function getCommandClass(): string
    {
        return CreateTodoCommand::class;
    }

    public function getBoundedContext(): string
    {
        return 'Todo';
    }
}
