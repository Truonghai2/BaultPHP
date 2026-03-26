# DDD Patterns Worth Implementing in BaultFrame 🎯

## 📊 Analysis Summary

**Source:** ddd-hexagonal-cqrs-es-eda-main  
**Current BaultFrame Status:** Has CQRS, Event Sourcing basics  
**Missing Patterns:** 8 major patterns identified  
**Priority:** HIGH - These will significantly improve architecture

---

## ✅ What BaultFrame Already Has

| Pattern | Status | Notes |
|---------|--------|-------|
| **CQRS** | ✅ Complete | CommandBus, QueryBus, Handlers |
| **Event Sourcing** | ✅ Basic | EventStore, EventSourcedAggregate |
| **Result Pattern** | ✅ Complete | Railway Oriented Programming |
| **gRPC** | ✅ Complete | Server, Client, CQRS integration |
| **Queue System** | ✅ Advanced | Batching, Chaining, Middleware |
| **Repository Pattern** | ✅ Basic | Read/Write separation |

---

## 🎯 Top 8 Patterns to Implement

### 1. **Domain Rules** ⭐⭐⭐⭐⭐

**Priority:** CRITICAL  
**Effort:** Low  
**Impact:** High

#### What It Is

```typescript
// From DDD Reference
export class TodoAlreadyCompletedRule implements Domain.IRule {
  constructor(private completed: boolean, private todoId: string) {}

  public Error = new DomainErrors.TodoAlreadyCompletedError(this.todoId);

  public isBrokenIf(): boolean {
    return this.completed; // Business rule check
  }
}

// Usage in Entity
class Todo {
  complete(): Result {
    const rule = new TodoAlreadyCompletedRule(this.completed, this.id);
    
    if (rule.isBrokenIf()) {
      return Result::fail(rule.Error);
    }
    
    // Execute business logic
    this.completed = true;
    return Result::ok();
  }
}
```

#### Why Important

- ✅ **Explicit Business Rules** - Rules are first-class citizens
- ✅ **Reusable** - Rules can be tested independently
- ✅ **Self-documenting** - Clear what rules apply
- ✅ **Domain-focused** - Business logic stays in domain

#### Implementation for BaultFrame

```php
// Core/Domain/Rule.php
interface DomainRule
{
    public function isBrokenIf(): bool;
    public function getError(): DomainError;
}

// Modules/Todo/Domain/Rules/TodoAlreadyCompletedRule.php
class TodoAlreadyCompletedRule implements DomainRule
{
    public function __construct(
        private bool $completed,
        private string $todoId
    ) {}

    public function isBrokenIf(): bool
    {
        return $this->completed;
    }

    public function getError(): DomainError
    {
        return new TodoAlreadyCompletedError($this->todoId);
    }
}

// Usage in Todo Entity
class Todo
{
    public function complete(): Result
    {
        $rule = new TodoAlreadyCompletedRule($this->completed, $this->id);
        
        if ($rule->isBrokenIf()) {
            return Result::fail($rule->getError());
        }
        
        $this->completed = true;
        $this->addDomainEvent(new TodoCompleted($this->id));
        
        return Result::ok();
    }
}
```

**Files to Create:**
1. `src/Core/Domain/DomainRule.php` (interface)
2. `src/Core/Domain/DomainError.php` (base class)
3. `Modules/Todo/Domain/Rules/*.php` (concrete rules)

---

### 2. **Integration Events** ⭐⭐⭐⭐⭐

**Priority:** CRITICAL  
**Effort:** Medium  
**Impact:** Very High

#### What It Is

**Cross-Bounded-Context Communication:**

```typescript
// Integration Event (published to other contexts)
export class TodoCompletedIntegrationEvent extends IntegrationEvent {
  static versions = ['v1']; // Versioning support!
  
  constructor(payload: IntegrationSchemaV1, version: string) {
    super('Todo', payload, version);
  }

  static create(event: TodoCompletedDomainEvent): TodoCompletedIntegrationEvent[] {
    return this.versions.map(version => {
      const mapper = this.versionMappers[version];
      const data = mapper(event);
      return new TodoCompletedIntegrationEvent(data, version);
    });
  }
}

// Marketing context listens
@IntegrationEventHandler(TodoCompletedIntegrationEvent)
class IncrementUserTodosHandler {
  async handle(event: TodoCompletedIntegrationEvent) {
    // Update marketing analytics
    await this.userRepo.incrementCompletedTodos(event.payload.userId);
  }
}
```

#### Why Important

- ✅ **Loose Coupling** - Contexts don't know about each other
- ✅ **Versioning** - Multiple versions supported (backward compatibility)
- ✅ **Async Communication** - Events via message bus
- ✅ **Scalability** - Each context can scale independently

#### Flow Diagram

```
Todo Context                    Marketing Context
─────────────                   ─────────────────
Command Handler
    ↓
Domain Logic
    ↓
TodoCompleted (Domain Event)
    ↓
TodoCompletedIntegrationEvent ─────→ IncrementTodosHandler
    ↓                                      ↓
Published to Bus                    Update Analytics
```

#### Implementation for BaultFrame

```php
// Core/Events/IntegrationEvent.php
abstract class IntegrationEvent
{
    protected string $boundedContext;
    protected string $version;
    protected array $payload;
    protected int $occurredAt;

    public function __construct(
        string $boundedContext,
        array $payload,
        string $version = 'v1'
    ) {
        $this->boundedContext = $boundedContext;
        $this->payload = $payload;
        $this->version = $version;
        $this->occurredAt = time();
    }

    abstract public static function fromDomainEvent($domainEvent): array;
    abstract public function getEventName(): string;
}

// Modules/Todo/Contracts/IntegrationEvents/TodoCompletedIntegrationEvent.php
class TodoCompletedIntegrationEvent extends IntegrationEvent
{
    public static array $versions = ['v1'];

    public static function fromDomainEvent(TodoCompleted $event): array
    {
        return [
            new self(
                'Todo',
                [
                    'todo_id' => $event->todoId,
                    'user_id' => $event->userId,
                    'completed_at' => $event->completedAt,
                ],
                'v1'
            )
        ];
    }

    public function getEventName(): string
    {
        return 'todo.completed.' . $this->version;
    }
}

// Modules/Marketing/Application/EventHandlers/TodoCompletedIntegrationHandler.php
class TodoCompletedIntegrationHandler
{
    public function handle(TodoCompletedIntegrationEvent $event): void
    {
        // Cross-context logic
        $this->userStatsRepo->incrementCompletedTodos($event->payload['user_id']);
        
        // Maybe send notification
        $this->notificationService->congratulate($event->payload['user_id']);
    }
}
```

**Files to Create:**
1. `src/Core/Events/IntegrationEvent.php`
2. `src/Core/Events/IntegrationEventBus.php`
3. `Modules/*/Contracts/IntegrationEvents/*.php`
4. `Modules/*/Application/EventHandlers/Integration/*.php`

---

### 3. **Correlation ID & Distributed Tracing** ⭐⭐⭐⭐

**Priority:** HIGH  
**Effort:** Medium  
**Impact:** High

#### What It Is

```typescript
// Correlation ID Interceptor
@Injectable()
export class CorrelationIdInterceptor implements NestInterceptor {
  async intercept(context: ExecutionContext, next: CallHandler) {
    const correlationId: string = randomUUID();
    
    // Store in AsyncLocalStorage
    const store = new Map();
    store.set('correlationId', correlationId);
    
    return asyncLocalStorage.run(store, () => {
      return next.handle(); // All code has access to correlationId!
    });
  }
}

// Usage anywhere in the request lifecycle
const correlationId = asyncLocalStorage.getStore().get('correlationId');
logger.info('Processing command', { correlationId });
```

#### Why Important

- ✅ **Distributed Tracing** - Track requests across services
- ✅ **Debugging** - Find all logs for one request
- ✅ **Observability** - End-to-end request tracking
- ✅ **No Manual Passing** - Automatic via AsyncLocalStorage

#### Flow

```
HTTP Request → CorrelationID Middleware
                     ↓
            Store in AsyncLocalStorage
                     ↓
        ┌────────────┼────────────┐
        ↓            ↓            ↓
   Controller   CommandBus    Logger
        ↓            ↓            ↓
   All have access to correlationId!
```

#### Implementation for BaultFrame

```php
// Core/Http/Middleware/CorrelationIdMiddleware.php
class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Get or generate correlation ID
        $correlationId = $request->header('X-Correlation-ID') 
            ?? Str::uuid();

        // Store in context (using Swoole Coroutine Context)
        \Swoole\Coroutine::getContext()['correlation_id'] = $correlationId;

        // Add to response
        $response = $next($request);
        $response->header('X-Correlation-ID', $correlationId);

        return $response;
    }
}

// Core/Support/Context.php
class Context
{
    public static function getCorrelationId(): ?string
    {
        $ctx = \Swoole\Coroutine::getContext();
        return $ctx['correlation_id'] ?? null;
    }

    public static function set(string $key, $value): void
    {
        \Swoole\Coroutine::getContext()[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return \Swoole\Coroutine::getContext()[$key] ?? $default;
    }
}

// Usage in logging
class Logger
{
    public function log(string $level, string $message, array $context = []): void
    {
        $context['correlation_id'] = Context::getCorrelationId();
        $context['user_id'] = Context::get('user_id');
        
        // Log with context
        $this->logger->log($level, $message, $context);
    }
}
```

**Files to Create:**
1. `src/Core/Http/Middleware/CorrelationIdMiddleware.php`
2. `src/Core/Support/Context.php` (Swoole Coroutine Context wrapper)
3. Update Logger to include correlation ID

---

### 4. **Test Builders Pattern** ⭐⭐⭐⭐

**Priority:** HIGH  
**Effort:** Low  
**Impact:** High (Developer Experience)

#### What It Is

```typescript
// Test Builder
export class TodoBuilder {
  private id = 'default-id';
  private title = 'Default Title';
  private completed = false;

  public withId(id: string): this {
    this.id = id;
    return this;
  }

  public withTitle(title: string): this {
    this.title = title;
    return this;
  }

  public completed(): this {
    this.completed = true;
    return this;
  }

  public build(): Todo {
    return Todo.fromPrimitives({
      id: this.id,
      title: this.title,
      completed: this.completed,
    });
  }
}

// Usage in tests
test('should complete todo', () => {
  const todo = new TodoBuilder()
    .withTitle('Buy milk')
    .build(); // Clean!

  todo.complete();

  expect(todo.isCompleted()).toBe(true);
});
```

#### Why Important

- ✅ **Test Readability** - Clear what's being tested
- ✅ **Reusability** - One builder for all tests
- ✅ **Maintainability** - Change in one place
- ✅ **Fluent API** - Easy to use

#### Implementation for BaultFrame

```php
// Modules/Todo/Tests/Builders/TodoBuilder.php
class TodoBuilder
{
    private string $id;
    private string $title = 'Default Title';
    private string $userId = 'user-123';
    private bool $completed = false;
    private int $createdAt;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
        $this->createdAt = time();
    }

    public function withId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function completed(): self
    {
        $this->completed = true;
        return $this;
    }

    public function build(): Todo
    {
        return Todo::fromArray([
            'id' => $this->id,
            'title' => $this->title,
            'user_id' => $this->userId,
            'completed' => $this->completed,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completed ? time() : null,
        ]);
    }
}

// Usage in tests
class TodoTest extends TestCase
{
    public function test_can_complete_todo()
    {
        // Arrange - Fluent & readable!
        $todo = (new TodoBuilder())
            ->withTitle('Buy groceries')
            ->build();

        // Act
        $result = $todo->complete();

        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($todo->isCompleted());
    }
}
```

**Files to Create:**
1. `Modules/*/Tests/Builders/*.php` (one builder per entity)

---

### 5. **Server-Sent Events (SSE)** ⭐⭐⭐⭐

**Priority:** MEDIUM  
**Effort:** Medium  
**Impact:** High (Real-time features)

#### What It Is

```typescript
// SSE Controller
@Controller('sse')
export class TodoSseController {
  @Sse('todos')
  async todoStream(@AuthData() authData): Observable<MessageEvent> {
    return this.eventBus.pipe(
      filter(event => event.userId === authData.userId),
      map(event => ({
        data: event.payload,
        type: event.eventName,
      })),
    );
  }
}

// Frontend usage
const eventSource = new EventSource('/sse/todos');

eventSource.addEventListener('todo.completed', (e) => {
  const data = JSON.parse(e.data);
  updateUI(data); // Real-time update!
});
```

#### Why Important

- ✅ **Real-time Updates** - Push to clients automatically
- ✅ **Simpler than WebSocket** - One-way communication
- ✅ **Auto Reconnect** - Browser handles it
- ✅ **Event-Driven UI** - React to events

#### Implementation for BaultFrame (Already have SSEController!)

```php
// src/Http/Controllers/SSEController.php (ALREADY EXISTS!)
// Just need to integrate with event system

// Modules/Todo/Http/Controllers/TodoSSEController.php
class TodoSSEController
{
    public function stream(Request $request): Response
    {
        $userId = auth()->id();

        return SSEStream::create(function() use ($userId) {
            // Subscribe to events
            $events = EventBus::subscribe([
                'todo.created',
                'todo.completed',
                'todo.deleted',
            ]);

            foreach ($events as $event) {
                if ($event->userId === $userId) {
                    yield [
                        'event' => $event->eventName(),
                        'data' => json_encode($event->toArray()),
                    ];
                }
            }
        });
    }
}
```

**Enhancement Needed:**
1. ✅ Already have `SSEController.php`
2. ✅ Already have `SSEStream.php`
3. ⚠️ Need to integrate with Event System
4. ⚠️ Need event filtering by user

---

### 6. **Bounded Context Modules** ⭐⭐⭐⭐

**Priority:** MEDIUM  
**Effort:** High  
**Impact:** Very High (Architecture)

#### What It Is

```
bounded-contexts/
├── todo/                   # Todo Bounded Context
│   └── todo/
│       ├── application/    # Use cases
│       ├── commands/       # Commands
│       ├── contracts/      # Integration contracts
│       ├── domain/         # Domain logic
│       ├── ports/          # Interfaces
│       └── queries/        # Queries
│
├── marketing/              # Marketing Bounded Context
│   └── marketing/
│       └── (same structure)
│
└── iam/                    # IAM Bounded Context
    └── iam/
        └── (same structure)
```

#### Why Important

- ✅ **Clear Boundaries** - Each context is independent
- ✅ **Team Ownership** - Different teams own different contexts
- ✅ **Deploy Independently** - Microservices ready
- ✅ **Ubiquitous Language** - Each context has its own terminology

**BaultFrame Already Has This!** ✅
- `Modules/Todo` ✅
- `Modules/User` ✅
- `Modules/Cms` ✅

**Just needs:**
- ⚠️ Better separation (contracts folder)
- ⚠️ Integration events between modules

---

### 7. **Message Bus with NATS** ⭐⭐⭐

**Priority:** LOW (for now)  
**Effort:** High  
**Impact:** High (when scaling)

#### What It Is

NATS JetStream for async messaging between contexts:

```typescript
// Publish integration event to NATS
@IntegrationEventHandler(TodoCompletedIntegrationEvent)
class TodoCompletedPublisher {
  async handle(event) {
    await this.natsClient.publish(
      'todo.completed.v1',
      event.payload
    );
  }
}

// Subscribe from another service
@NatsSubscribe('todo.completed.v1')
class TodoCompletedConsumer {
  async handle(data) {
    // Process in Marketing service
  }
}
```

**BaultFrame Alternative:**
- ✅ Use Redis Pub/Sub (already have)
- ✅ Use Queue System (already have)
- ⏳ NATS later when need true message streaming

---

### 8. **Advanced Testing Patterns** ⭐⭐⭐

**Priority:** MEDIUM  
**Effort:** Medium  
**Impact:** High

From reference:
- **Mock Repositories** - In-memory for testing
- **Test Fixtures** - Predefined test data
- **Integration Tests** - End-to-end bounded context tests

```typescript
// Mock Repository for testing
export class InMemoryTodoRepository implements TodoWriteRepository {
  private todos: Map<string, Todo> = new Map();

  async save(todo: Todo): Promise<void> {
    this.todos.set(todo.id, todo);
  }

  async findById(id: string): Promise<Todo | null> {
    return this.todos.get(id) || null;
  }

  clear(): void {
    this.todos.clear();
  }
}

// Test
test('should create todo', async () => {
  const repo = new InMemoryTodoRepository();
  const handler = new CreateTodoHandler(repo);

  await handler.handle(new CreateTodoCommand('Buy milk'));

  const todos = await repo.getAll();
  expect(todos).toHaveLength(1);
});
```

---

## 📊 Implementation Priority Matrix

| Pattern | Priority | Effort | Impact | Status |
|---------|----------|--------|--------|--------|
| **Domain Rules** | ⭐⭐⭐⭐⭐ | Low | High | 🔴 Missing |
| **Integration Events** | ⭐⭐⭐⭐⭐ | Medium | Very High | 🔴 Missing |
| **Correlation ID** | ⭐⭐⭐⭐ | Medium | High | 🔴 Missing |
| **Test Builders** | ⭐⭐⭐⭐ | Low | High | 🔴 Missing |
| **SSE Integration** | ⭐⭐⭐⭐ | Low | High | 🟡 Partial |
| **Bounded Contexts** | ⭐⭐⭐⭐ | - | - | ✅ Have |
| **NATS Message Bus** | ⭐⭐⭐ | High | High | 🟡 Alternative (Redis) |
| **Advanced Testing** | ⭐⭐⭐ | Medium | High | 🔴 Missing |

---

## 🎯 Recommended Implementation Order

### Phase 1: Foundation (Week 1)
1. ✅ **Domain Rules** - 2 days
2. ✅ **Correlation ID & Context** - 2 days
3. ✅ **Test Builders** - 1 day

### Phase 2: Integration (Week 2)
4. ✅ **Integration Events** - 3 days
5. ✅ **SSE Event Integration** - 2 days

### Phase 3: Advanced (Week 3+)
6. ✅ **Advanced Testing Patterns** - 3 days
7. ⏳ **NATS Integration** - Later (if needed)

---

## 💡 Quick Wins (Do First!)

### 1. Domain Rules (2 hours!)

```php
// Just 3 files needed!

// 1. Interface
interface DomainRule {
    public function isBrokenIf(): bool;
    public function getError(): DomainError;
}

// 2. Base Error
abstract class DomainError extends \RuntimeException {}

// 3. Concrete Rule
class TodoAlreadyCompletedRule implements DomainRule {
    public function __construct(private bool $completed) {}
    
    public function isBrokenIf(): bool {
        return $this->completed;
    }
    
    public function getError(): DomainError {
        return new TodoAlreadyCompletedError();
    }
}

// Usage
$rule = new TodoAlreadyCompletedRule($this->completed);
if ($rule->isBrokenIf()) {
    return Result::fail($rule->getError());
}
```

### 2. Correlation ID (1 hour!)

```php
// Just use Swoole Coroutine Context!

class Context {
    public static function getCorrelationId(): ?string {
        return \Swoole\Coroutine::getContext()['correlation_id'] ?? null;
    }
}

// Middleware
$correlationId = Str::uuid();
\Swoole\Coroutine::getContext()['correlation_id'] = $correlationId;
```

---

## 🎉 Summary

### What Makes These Patterns Valuable

1. **Domain Rules** → Explicit business logic
2. **Integration Events** → Loose coupling between contexts
3. **Correlation ID** → Distributed tracing
4. **Test Builders** → Better tests
5. **SSE** → Real-time updates

### What BaultFrame Will Gain

- ✅ **Better Architecture** - Cleaner separation of concerns
- ✅ **Easier Testing** - Builders + mocks
- ✅ **Better Observability** - Correlation ID tracing
- ✅ **Real-time Features** - SSE events
- ✅ **Scalability** - Integration events for microservices

### Estimated Total Effort

- **Phase 1 (Foundation):** 5 days
- **Phase 2 (Integration):** 5 days
- **Phase 3 (Advanced):** 3 days

**Total:** ~2-3 weeks for complete implementation

---

**Status:** 📋 **Ready to Implement**  
**ROI:** 🚀 **Very High**  
**Complexity:** 📊 **Medium**

**Let's start with Domain Rules and Correlation ID - the quick wins! 🎯**
