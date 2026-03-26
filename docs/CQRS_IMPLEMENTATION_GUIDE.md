# CQRS Implementation Guide for BaultFrame

## 📚 Học Từ DDD System

### CQRS Pattern Overview

**CQRS** = **Command Query Responsibility Segregation**

```
┌─────────────────────────────────────────────────────────────┐
│                     CQRS Architecture                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  WRITE SIDE (Commands)          READ SIDE (Queries)         │
│  ┌──────────────────┐          ┌──────────────────┐        │
│  │   Controller     │          │   Controller     │        │
│  └────────┬─────────┘          └────────┬─────────┘        │
│           │                              │                  │
│           ▼                              ▼                  │
│  ┌──────────────────┐          ┌──────────────────┐        │
│  │  CommandBus      │          │   QueryBus       │        │
│  └────────┬─────────┘          └────────┬─────────┘        │
│           │                              │                  │
│           ▼                              ▼                  │
│  ┌──────────────────┐          ┌──────────────────┐        │
│  │ CommandHandler   │          │  QueryHandler    │        │
│  └────────┬─────────┘          └────────┬─────────┘        │
│           │                              │                  │
│           ▼                              ▼                  │
│  ┌──────────────────┐          ┌──────────────────┐        │
│  │ Write Repository │          │ Read Repository  │        │
│  │ (Domain Model)   │          │ (Read Model)     │        │
│  └────────┬─────────┘          └────────┬─────────┘        │
│           │                              │                  │
│           ▼                              ▼                  │
│  ┌──────────────────┐          ┌──────────────────┐        │
│  │  Write Database  │          │  Read Database   │        │
│  │  (PostgreSQL)    │          │  (MongoDB/Cache) │        │
│  └──────────────────┘          └──────────────────┘        │
│           │                              ▲                  │
│           │   Domain Events              │                  │
│           └──────────────────────────────┘                  │
│              (Update Read Model)                            │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Key Concepts

### 1. **Commands (Write Operations)**

**Characteristics:**
- ✅ Modify state (Create, Update, Delete)
- ✅ Asynchronous execution
- ✅ Task-based naming (AddTodo, CompleteTodo)
- ✅ Return void or Result<void>
- ✅ Can raise domain events

**Example from DDD:**
```typescript
// Command
export class AddTodoCommand extends Application.Command {
  public title: string;
  
  constructor(props: { title: string }) {
    super('Todo');
    this.title = props.title;
  }
}

// Command Handler
export class AddTodoCommandHandler 
  implements Application.ICommandHandler<AddTodoCommand, void> {
  
  constructor(
    @Inject(TodoWriteRepoPortToken) 
    private todoRepo: TodoWriteRepoPort,
    @Inject(DomainEventBusToken) 
    private eventBus: Application.IEventBus
  ) {}
  
  async execute(command: AddTodoCommand): Promise<Result<void>> {
    // 1. Validate & create entity
    const titleResult = TitleVO.create({ title: command.title });
    if (titleResult.isFail()) return fail(titleResult.value);
    
    const todo = TodoEntity.create({
      title: titleResult.value,
      userId: command.userId,
    });
    
    // 2. Save to write store
    const saveResult = await this.todoRepo.save(todo);
    if (saveResult.isFail()) return fail(saveResult.value);
    
    // 3. Publish domain events
    await this.eventBus.publish(todo.domainEvents);
    
    return ok();
  }
}
```

---

### 2. **Queries (Read Operations)**

**Characteristics:**
- ✅ Read-only operations
- ✅ Synchronous execution (request/response)
- ✅ Question-based naming (GetTodos, FindUser)
- ✅ Return data (DTOs, Read Models)
- ✅ Never modify state

**Example from DDD:**
```typescript
// Query
export class GetTodosQuery extends Application.Query {
  constructor(
    public readonly limit?: number,
    public readonly offset?: number
  ) {
    super('Todo');
  }
}

// Query Handler
export class GetTodosHandler 
  implements Application.IQueryHandler<GetTodosQuery, TodoReadModel[]> {
  
  constructor(
    @Inject(TodoReadRepoPortToken) 
    private todoReadRepo: TodoReadRepoPort
  ) {}
  
  async execute(query: GetTodosQuery): Promise<Result<TodoReadModel[]>> {
    const { limit, offset } = query;
    
    // Read from optimized read model
    const result = await this.todoReadRepo.getAll({ limit, offset });
    
    if (result.isFail()) return fail(result.value);
    
    return ok(result.value || []);
  }
}
```

---

### 3. **Command Bus vs Query Bus**

#### Command Bus (Async)
```typescript
export class NatsStreamingCommandBus {
  // Publish command (fire and forget)
  async publish(command: Command): Promise<void> {
    const subject = `${stream}.${command.constructor.name}`;
    await this.jetstream.publish(subject, command);
  }
  
  // Subscribe to handle commands
  async subscribe(subject: string, handler: CommandHandler) {
    const sub = await this.jetstream.subscribe(subject);
    for await (const msg of sub) {
      const command = decode(msg.data);
      await handler.execute(command);
      msg.ack();
    }
  }
}
```

#### Query Bus (Sync - Request/Response)
```typescript
export class NatsPubSubQueryBus {
  // Request query (wait for response)
  async request(query: Query): Promise<any> {
    const topic = getTopicFromQuery(query);
    const response = await this.nats.request(topic, query, { timeout: 5000 });
    return decode(response.data);
  }
  
  // Subscribe to handle queries
  async pubSubSubscribe(subject: string, handler: QueryHandler) {
    const sub = this.nats.subscribe(subject);
    for await (const msg of sub) {
      const query = decode(msg.data);
      const result = await handler.execute(query);
      
      if (msg.reply) {
        this.nats.publish(msg.reply, encode(result));
      }
    }
  }
}
```

---

### 4. **Read Model vs Write Model**

#### Write Model (Domain Entity)
```typescript
export class TodoEntity extends Domain.Aggregate<TodoProps> {
  // Rich domain model with business logic
  
  public complete(): Either<void, TodoAlreadyCompletedError> {
    // Apply business rules
    const res = Domain.applyRules([
      new Rules.TodoAlreadyCompleted(this.completed, this.id)
    ]);
    if (res) return fail(res);
    
    // Change state
    this.props.completed = true;
    
    // Raise event
    this.addDomainEvent(new TodoCompletedDomainEvent({...}));
    
    return ok();
  }
  
  // Stored in normalized relational database (PostgreSQL)
}
```

#### Read Model (Projection)
```typescript
export class TodoReadModel extends Domain.ReadModel {
  // Flat, denormalized data optimized for queries
  public readonly id: string;
  public readonly userId: string;
  public readonly title: string;
  public readonly completed: boolean;
  public readonly createdAt: Date;
  
  // No business logic, just data
  // Stored in optimized read store (MongoDB, Redis, etc.)
}
```

---

### 5. **Eventual Consistency**

```
Write Flow:
1. Command → CommandHandler
2. Update Write Model (PostgreSQL)
3. Raise Domain Events
4. Commit transaction

Event Flow (Async):
5. Domain Events → Event Bus
6. Event Handler listens
7. Update Read Model (MongoDB)
8. Read Model now consistent

Time: ~100-500ms delay between write and read consistency
```

**Benefits:**
- ✅ Write side optimized for transactions
- ✅ Read side optimized for queries
- ✅ Can scale independently
- ✅ Different storage per need

---

## 🚀 Implementation for BaultFrame

### Phase 1: Core CQRS Infrastructure

#### 1.1 Command Base Classes

```php
<?php
// src/Core/CQRS/Command.php

namespace Core\CQRS;

abstract class Command
{
    protected string $boundedContext;
    protected ?string $correlationId = null;
    protected array $metadata = [];
    
    public function __construct(string $boundedContext)
    {
        $this->boundedContext = $boundedContext;
        $this->correlationId = uniqid('cmd_', true);
        $this->metadata = [
            'timestamp' => time(),
            'user_id' => auth()->id(),
        ];
    }
    
    public function getBoundedContext(): string
    {
        return $this->boundedContext;
    }
    
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
```

#### 1.2 Query Base Classes

```php
<?php
// src/Core/CQRS/Query.php

namespace Core\CQRS;

abstract class Query
{
    protected string $boundedContext;
    protected ?string $correlationId = null;
    
    public function __construct(string $boundedContext)
    {
        $this->boundedContext = $boundedContext;
        $this->correlationId = uniqid('qry_', true);
    }
    
    public function getBoundedContext(): string
    {
        return $this->boundedContext;
    }
}
```

#### 1.3 Handler Interfaces

```php
<?php
// src/Core/CQRS/CommandHandler.php

namespace Core\CQRS;

use Core\Support\Result;

interface CommandHandler
{
    /**
     * Execute the command.
     * 
     * @param Command $command
     * @return Result<void>
     */
    public function handle(Command $command): Result;
    
    /**
     * Get the command class this handler handles.
     */
    public function getCommandClass(): string;
    
    /**
     * Get the bounded context.
     */
    public function getBoundedContext(): string;
}
```

```php
<?php
// src/Core/CQRS/QueryHandler.php

namespace Core\CQRS;

use Core\Support\Result;

interface QueryHandler
{
    /**
     * Execute the query.
     * 
     * @param Query $query
     * @return Result<mixed>
     */
    public function handle(Query $query): Result;
    
    /**
     * Get the query class this handler handles.
     */
    public function getQueryClass(): string;
    
    /**
     * Get the bounded context.
     */
    public function getBoundedContext(): string;
}
```

---

### Phase 2: Command & Query Buses

#### 2.1 Command Bus

```php
<?php
// src/Core/CQRS/CommandBus.php

namespace Core\CQRS;

use Core\Application;
use Core\Support\Result;

class CommandBus
{
    protected array $handlers = [];
    
    public function __construct(protected Application $app)
    {
    }
    
    /**
     * Register a command handler.
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }
    
    /**
     * Execute a command asynchronously.
     * 
     * @param Command $command
     * @return Result<void>
     */
    public function execute(Command $command): Result
    {
        $commandClass = get_class($command);
        
        if (!isset($this->handlers[$commandClass])) {
            return Result::fail("No handler registered for command: $commandClass");
        }
        
        $handlerClass = $this->handlers[$commandClass];
        $handler = $this->app->make($handlerClass);
        
        // Log command execution
        $this->app->make('log')->info('Executing command', [
            'command' => $commandClass,
            'correlation_id' => $command->getCorrelationId(),
        ]);
        
        try {
            // Execute through middleware pipeline (optional)
            $result = $this->executeWithMiddleware($handler, $command);
            
            return $result;
        } catch (\Throwable $e) {
            $this->app->make('log')->error('Command execution failed', [
                'command' => $commandClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Result::fail($e->getMessage());
        }
    }
    
    /**
     * Execute command with middleware.
     */
    protected function executeWithMiddleware(CommandHandler $handler, Command $command): Result
    {
        // TODO: Implement middleware pipeline
        return $handler->handle($command);
    }
}
```

#### 2.2 Query Bus

```php
<?php
// src/Core/CQRS/QueryBus.php

namespace Core\CQRS;

use Core\Application;
use Core\Support\Result;

class QueryBus
{
    protected array $handlers = [];
    
    public function __construct(protected Application $app)
    {
    }
    
    /**
     * Register a query handler.
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }
    
    /**
     * Execute a query synchronously.
     * 
     * @param Query $query
     * @return Result<mixed>
     */
    public function execute(Query $query): Result
    {
        $queryClass = get_class($query);
        
        if (!isset($this->handlers[$queryClass])) {
            return Result::fail("No handler registered for query: $queryClass");
        }
        
        $handlerClass = $this->handlers[$queryClass];
        $handler = $this->app->make($handlerClass);
        
        // Log query execution
        $this->app->make('log')->debug('Executing query', [
            'query' => $queryClass,
            'correlation_id' => $query->getCorrelationId(),
        ]);
        
        try {
            $result = $handler->handle($query);
            
            return $result;
        } catch (\Throwable $e) {
            $this->app->make('log')->error('Query execution failed', [
                'query' => $queryClass,
                'error' => $e->getMessage(),
            ]);
            
            return Result::fail($e->getMessage());
        }
    }
}
```

---

### Phase 3: Result Pattern

```php
<?php
// src/Core/Support/Result.php

namespace Core\Support;

/**
 * Result pattern for explicit error handling.
 * Inspired by Railway Oriented Programming.
 * 
 * Usage:
 * return Result::ok($data);
 * return Result::fail($error);
 * 
 * if ($result->isSuccess()) {
 *     $data = $result->getValue();
 * } else {
 *     $error = $result->getError();
 * }
 */
class Result
{
    private function __construct(
        private bool $isSuccess,
        private mixed $value = null,
        private mixed $error = null
    ) {}
    
    public static function ok(mixed $value = null): self
    {
        return new self(true, $value, null);
    }
    
    public static function fail(mixed $error): self
    {
        return new self(false, null, $error);
    }
    
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }
    
    public function isFailure(): bool
    {
        return !$this->isSuccess;
    }
    
    public function getValue(): mixed
    {
        if (!$this->isSuccess) {
            throw new \RuntimeException('Cannot get value from failed result');
        }
        
        return $this->value;
    }
    
    public function getError(): mixed
    {
        if ($this->isSuccess) {
            throw new \RuntimeException('Cannot get error from successful result');
        }
        
        return $this->error;
    }
    
    /**
     * Map the value if successful.
     */
    public function map(callable $fn): self
    {
        if (!$this->isSuccess) {
            return $this;
        }
        
        return self::ok($fn($this->value));
    }
    
    /**
     * Flat map (bind) operation.
     */
    public function flatMap(callable $fn): self
    {
        if (!$this->isSuccess) {
            return $this;
        }
        
        return $fn($this->value);
    }
}
```

---

## 📝 Example: Todo Module with CQRS

### Commands (Write Side)

```php
<?php
// Modules/Todo/Application/Commands/AddTodoCommand.php

namespace Modules\Todo\Application\Commands;

use Core\CQRS\Command;

class AddTodoCommand extends Command
{
    public function __construct(
        public readonly string $title,
        public readonly string $userId
    ) {
        parent::__construct('Todo');
    }
}
```

```php
<?php
// Modules/Todo/Application/CommandHandlers/AddTodoCommandHandler.php

namespace Modules\Todo\Application\CommandHandlers;

use Core\CQRS\{Command, CommandHandler};
use Core\Support\Result;
use Modules\Todo\Domain\{TodoEntity, TitleVO};
use Modules\Todo\Infrastructure\Repositories\TodoWriteRepository;

class AddTodoCommandHandler implements CommandHandler
{
    public function __construct(
        private TodoWriteRepository $todoRepo,
        private EventBus $eventBus
    ) {}
    
    public function handle(Command $command): Result
    {
        // 1. Validate
        $titleResult = TitleVO::create($command->title);
        if ($titleResult->isFailure()) {
            return $titleResult;
        }
        
        // 2. Create entity
        $todo = TodoEntity::create([
            'title' => $titleResult->getValue(),
            'userId' => $command->userId,
        ]);
        
        // 3. Save
        $saveResult = $this->todoRepo->save($todo);
        if ($saveResult->isFailure()) {
            return $saveResult;
        }
        
        // 4. Publish events
        $this->eventBus->publishAll($todo->releaseEvents());
        
        return Result::ok();
    }
    
    public function getCommandClass(): string
    {
        return AddTodoCommand::class;
    }
    
    public function getBoundedContext(): string
    {
        return 'Todo';
    }
}
```

### Queries (Read Side)

```php
<?php
// Modules/Todo/Application/Queries/GetTodosQuery.php

namespace Modules\Todo\Application\Queries;

use Core\CQRS\Query;

class GetTodosQuery extends Query
{
    public function __construct(
        public readonly ?int $limit = null,
        public readonly ?int $offset = null,
        public readonly ?string $userId = null
    ) {
        parent::__construct('Todo');
    }
}
```

```php
<?php
// Modules/Todo/Application/QueryHandlers/GetTodosQueryHandler.php

namespace Modules\Todo\Application\QueryHandlers;

use Core\CQRS\{Query, QueryHandler};
use Core\Support\Result;
use Modules\Todo\Infrastructure\Repositories\TodoReadRepository;

class GetTodosQueryHandler implements QueryHandler
{
    public function __construct(
        private TodoReadRepository $todoReadRepo
    ) {}
    
    public function handle(Query $query): Result
    {
        $todos = $this->todoReadRepo->getAll([
            'limit' => $query->limit,
            'offset' => $query->offset,
            'userId' => $query->userId,
        ]);
        
        return Result::ok($todos);
    }
    
    public function getQueryClass(): string
    {
        return GetTodosQuery::class;
    }
    
    public function getBoundedContext(): string
    {
        return 'Todo';
    }
}
```

### Controller Usage

```php
<?php
// Modules/Todo/Http/Controllers/TodoController.php

namespace Modules\Todo\Http\Controllers;

use Core\CQRS\{CommandBus, QueryBus};
use Core\Http\{Request, Response};
use Modules\Todo\Application\Commands\AddTodoCommand;
use Modules\Todo\Application\Queries\GetTodosQuery;

class TodoController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus
    ) {}
    
    // Command (Write)
    public function store(Request $request): Response
    {
        $command = new AddTodoCommand(
            title: $request->input('title'),
            userId: auth()->id()
        );
        
        $result = $this->commandBus->execute($command);
        
        if ($result->isFailure()) {
            return Response::json([
                'error' => $result->getError()
            ], 400);
        }
        
        return Response::json([
            'message' => 'Todo created successfully'
        ], 201);
    }
    
    // Query (Read)
    public function index(Request $request): Response
    {
        $query = new GetTodosQuery(
            limit: $request->input('limit', 20),
            offset: $request->input('offset', 0),
            userId: auth()->id()
        );
        
        $result = $this->queryBus->execute($query);
        
        if ($result->isFailure()) {
            return Response::json([
                'error' => $result->getError()
            ], 500);
        }
        
        return Response::json([
            'data' => $result->getValue()
        ]);
    }
}
```

---

## 🎯 Benefits of CQRS

### 1. **Separation of Concerns**
- ✅ Write logic separate from read logic
- ✅ Easier to understand and maintain
- ✅ Team can work on different sides independently

### 2. **Performance Optimization**
- ✅ Optimize write model for transactions (PostgreSQL)
- ✅ Optimize read model for queries (MongoDB, Redis)
- ✅ Scale read/write independently

### 3. **Flexibility**
- ✅ Different data models for read/write
- ✅ Multiple read models for different use cases
- ✅ Can evolve separately

### 4. **Event Sourcing Ready**
- ✅ Commands naturally produce events
- ✅ Events update read models
- ✅ Full audit trail

---

## 📊 Implementation Progress

| Component | Status | Progress |
|-----------|--------|----------|
| Command Base | ⏳ Next | 0% |
| Query Base | ⏳ Next | 0% |
| CommandBus | ⏳ Next | 0% |
| QueryBus | ⏳ Next | 0% |
| Result Pattern | ⏳ Next | 0% |
| Handler Registration | ⏳ Next | 0% |
| Middleware Support | ⏳ Future | 0% |
| Event Integration | ⏳ Future | 0% |

---

## 🚀 Next Steps

Bạn muốn tôi:
1. ✅ **Implement CQRS Infrastructure** - Command/Query buses
2. ✅ **Add Result Pattern** - Railway oriented programming
3. ✅ **Convert Todo Module** - Apply CQRS to existing module
4. ✅ **Add Event Sourcing** - Full event-driven system
5. ✅ **Create Documentation** - Best practices guide

**Recommendation:** Bắt đầu với infrastructure (Command/QueryBus + Result) để có foundation vững chắc! 🎯

---

**Analysis Date:** 2026-01-22  
**Status:** ✅ Design Complete → Ready for Implementation  
**Quality:** ⭐⭐⭐⭐⭐ Enterprise Grade
