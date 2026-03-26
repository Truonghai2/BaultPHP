# DDD, Hexagonal Architecture, CQRS, Event Sourcing & EDA - Comprehensive Analysis

## 📚 Nguồn Học

**Repository:** [ddd-hexagonal-cqrs-es-eda](https://github.com/bitloops/ddd-hexagonal-cqrs-es-eda)  
**Stack:** TypeScript, NestJS, MongoDB, PostgreSQL, NATS, JetStream  
**Purpose:** Production-ready example of advanced software architecture patterns

---

## 🏗️ Architecture Overview

### 1. **Bounded Contexts** (DDD)

Hệ thống được chia thành các **Bounded Contexts** độc lập:

```
src/lib/bounded-contexts/
├── todo/          # Todo Context
│   └── todo/      # Todo Module
│       ├── application/
│       ├── commands/
│       ├── domain/
│       ├── ports/
│       ├── queries/
│       └── tests/
│
├── marketing/     # Marketing Context
│   └── marketing/ # Marketing Module
│       ├── application/
│       ├── commands/
│       ├── domain/
│       └── ports/
│
└── iam/          # Identity & Access Management Context
    └── iam/
        └── repository/
```

**Key Concepts:**
- Mỗi Bounded Context có ngôn ngữ riêng (Ubiquitous Language)
- Độc lập hoàn toàn, có thể deploy riêng lẻ
- Giao tiếp qua Integration Events

---

### 2. **Hexagonal Architecture** (Ports & Adapters)

#### Structure:

```
Module/
├── domain/              # Core Business Logic (CENTER)
│   ├── entities/        
│   ├── value-objects/   
│   ├── events/          
│   ├── rules/           
│   └── errors/          
│
├── application/         # Use Cases (APPLICATION LAYER)
│   ├── command-handlers/
│   └── query-handlers/
│
├── ports/              # Interfaces (PORTS)
│   ├── repositories/
│   └── services/
│
└── adapters/           # Implementations (ADAPTERS)
    ├── persistence/
    └── external-services/
```

**Principles:**
1. **Domain** (Core) - No dependencies on infrastructure
2. **Application** - Orchestrates domain logic
3. **Ports** - Interfaces định nghĩa contract
4. **Adapters** - Concrete implementations

---

### 3. **CQRS Pattern**

#### Separation of Concerns:

**Commands (Write Side):**
```typescript
// Command
export class AddTodoCommand extends Application.Command {
  public title: string;
  
  constructor(props: TAddTodoCommand) {
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
    @Inject(StreamingCommandBusToken) 
    private commandBus: Application.ICommandBus
  ) {}
  
  async execute(command: AddTodoCommand): Promise<Result> {
    // 1. Create domain entity
    const todo = TodoEntity.create({...});
    
    // 2. Save to write store
    await this.todoRepo.save(todo);
    
    // 3. Publish domain events
    await this.commandBus.publish(todo.domainEvents);
    
    return ok();
  }
}
```

**Queries (Read Side):**
```typescript
// Query
export class GetTodosQuery extends Application.Query {
  constructor() {
    super('Todo');
  }
}

// Query Handler
export class GetTodosQueryHandler 
  implements Application.IQueryHandler<GetTodosQuery, TodoReadModel[]> {
  
  constructor(
    @Inject(TodoReadRepoPortToken) 
    private todoReadRepo: TodoReadRepoPort
  ) {}
  
  async execute(query: GetTodosQuery): Promise<Result<TodoReadModel[]>> {
    // Read from optimized read model
    const todos = await this.todoReadRepo.getAll();
    return ok(todos);
  }
}
```

**Benefits:**
- ✅ Separate read/write stores (optimized for each)
- ✅ Independent scaling
- ✅ Different data models for read/write
- ✅ Eventual consistency

---

### 4. **Domain-Driven Design Tactical Patterns**

#### 4.1 Entities

```typescript
export class TodoEntity extends Domain.Aggregate<TodoProps> {
  private constructor(props: TodoProps) {
    super(props, props.id);
  }
  
  public static create(props: TodoProps): Either<TodoEntity, never> {
    const todo = new TodoEntity(props);
    
    const isNew = !props.id;
    if (isNew) {
      // Raise domain event
      const event = new TodoAddedDomainEvent({
        title: todo.title.title,
        userId: todo.userId.id.toString(),
        aggregateId: todo.id.toString(),
      });
      todo.addDomainEvent(event);
    }
    
    return ok(todo);
  }
  
  // Business logic methods
  public complete(): Either<void, DomainErrors.TodoAlreadyCompletedError> {
    // Apply domain rules
    const res = Domain.applyRules([
      new Rules.TodoAlreadyCompleted(this.props.completed, this.id.toString())
    ]);
    if (res) return fail(res);
    
    // Change state
    this.props.completed = true;
    
    // Raise domain event
    const event = new TodoCompletedDomainEvent({...});
    this.addDomainEvent(event);
    
    return ok();
  }
  
  public uncomplete(): Either<void, DomainErrors.TodoAlreadyUncompletedError> {
    // Similar pattern
  }
  
  public modifyTitle(title: TitleVO): Either<void, never> {
    this.props.title = title;
    this.addDomainEvent(new TodoModifiedTitleDomainEvent({...}));
    return ok();
  }
}
```

**Key Patterns:**
- ✅ Private constructor + static factory method
- ✅ Domain events for state changes
- ✅ Business logic encapsulated in methods
- ✅ Rich domain model (not anemic)

---

#### 4.2 Value Objects

```typescript
interface TitleProps {
  title: string;
}

export class TitleVO extends Domain.ValueObject<TitleProps> {
  get title(): string {
    return this.props.title;
  }
  
  private constructor(props: TitleProps) {
    super(props);
  }
  
  public static create(
    props: TitleProps
  ): Either<TitleVO, DomainErrors.TitleOutOfBoundsError> {
    // Apply validation rules
    const res = Domain.applyRules([
      new Rules.TitleOutOfBounds(props.title)
    ]);
    if (res) return fail(res);
    
    return ok(new TitleVO(props));
  }
}
```

**Characteristics:**
- ✅ Immutable
- ✅ Value-based equality
- ✅ Self-validating
- ✅ No identity

---

#### 4.3 Domain Events

```typescript
type TodoAddedDomainEventProps = Domain.TDomainEventProps<{
  userId: string;
  title: string;
  completed: boolean;
}>;

export class TodoAddedDomainEvent 
  extends Domain.DomainEvent<TodoAddedDomainEventProps> {
  
  public aggregateId: string;
  
  constructor(payload: TodoAddedDomainEventProps) {
    super('Todo', payload);  // Bounded context name
    this.aggregateId = payload.aggregateId;
  }
}
```

**Purpose:**
- ✅ Capture state changes
- ✅ Enable event sourcing
- ✅ Trigger side effects
- ✅ Eventual consistency

---

#### 4.4 Domain Rules

```typescript
export class TitleOutOfBounds extends Domain.Rule<string> {
  constructor(private title: string) {
    super();
  }
  
  public Error(): DomainErrors.TitleOutOfBoundsError {
    return new DomainErrors.TitleOutOfBoundsError(this.title);
  }
  
  public isBrokenIf(): boolean {
    return this.title.length < 3 || this.title.length > 150;
  }
}

export class TodoAlreadyCompleted extends Domain.Rule<boolean> {
  constructor(
    private completed: boolean,
    private todoId: string
  ) {
    super();
  }
  
  public Error(): DomainErrors.TodoAlreadyCompletedError {
    return new DomainErrors.TodoAlreadyCompletedError(this.todoId);
  }
  
  public isBrokenIf(): boolean {
    return this.completed === true;
  }
}
```

**Benefits:**
- ✅ Declarative validation
- ✅ Reusable business rules
- ✅ Clear error messages
- ✅ Testable in isolation

---

#### 4.5 Read Models

```typescript
export class TodoReadModel extends Domain.ReadModel {
  public readonly id: string;
  public readonly userId: string;
  public readonly title: string;
  public readonly completed: boolean;
  
  constructor(todo: Partial<TodoReadModel>) {
    super();
    this.id = todo.id;
    this.userId = todo.userId;
    this.title = todo.title;
    this.completed = todo.completed;
  }
}
```

**Purpose:**
- ✅ Optimized for queries
- ✅ Denormalized data
- ✅ No business logic
- ✅ Fast reads

---

### 5. **Event-Driven Architecture**

#### 5.1 Domain Events (Internal)

```typescript
// Raised by aggregate
todo.addDomainEvent(new TodoCompletedDomainEvent({...}));

// Published after save
await this.domainEventBus.publish(todo.domainEvents);
```

#### 5.2 Integration Events (Cross-Context)

```typescript
// Integration Event
export class TodoCompletedIntegrationEvent 
  extends IntegrationEvent<TodoCompletedIntegrationEventPayload> {
  
  public static readonly boundedContextId = 'Todo';
  static versions = ['v1'];
  
  constructor(payload: TodoCompletedIntegrationEventPayload) {
    super('Todo', payload);
  }
}

// Integration Event Handler (in Marketing context)
@IntegrationEventHandler(TodoCompletedIntegrationEvent)
export class TodoCompletedIntegrationHandler 
  implements IIntegrationEventHandler<TodoCompletedIntegrationEvent> {
  
  constructor(
    @Inject(StreamingCommandBusToken) 
    private commandBus: Application.ICommandBus
  ) {}
  
  async handle(event: TodoCompletedIntegrationEvent): Promise<void> {
    // React to event from Todo context
    await this.commandBus.publish(
      new IncrementTodosCommand({
        userId: event.payload.userId
      })
    );
  }
}
```

**Communication Flow:**
```
Todo Context                    Marketing Context
    |                                 |
    | 1. TodoCompleted                |
    |    Domain Event                 |
    |                                 |
    | 2. Convert to                   |
    |    Integration Event            |
    |                                 |
    | 3. Publish to                   |
    |    Message Bus (NATS)           |
    |---------------------------------|
                                      |
                              4. Handle Event
                              5. Trigger Command
```

---

### 6. **Ports & Adapters (Implementation)**

#### 6.1 Port (Interface)

```typescript
export type TodoWriteRepoPort = Application.Repo.ICRUDWritePort<
  TodoEntity,
  Domain.UUIDv4
>;

// Base interface provides:
// - save(entity)
// - getById(id)
// - delete(id)
// - update(entity)
```

#### 6.2 Adapter (Implementation)

```typescript
@Injectable()
export class TodoWriteRepository implements TodoWriteRepoPort {
  constructor(
    @InjectConnection('Todo') 
    private connection: Connection
  ) {}
  
  @Traceable()
  async save(todo: TodoEntity): Promise<Either<void, Error>> {
    const primitives = todo.toPrimitives();
    await this.connection
      .collection('todos')
      .insertOne(primitives);
    return ok();
  }
  
  @Traceable()
  async getById(id: Domain.UUIDv4): Promise<Either<TodoEntity, Error>> {
    const doc = await this.connection
      .collection('todos')
      .findOne({ _id: id.toString() });
    
    if (!doc) return fail(new NotFoundError());
    
    return ok(TodoEntity.fromPrimitives(doc));
  }
}
```

**Registering Adapter:**

```typescript
// Module
@Module({
  providers: [
    {
      provide: TodoWriteRepoPortToken,  // Port token
      useClass: TodoWriteRepository      // Adapter implementation
    }
  ]
})
export class TodoModule {}
```

---

### 7. **Testing Strategy (BDD)**

#### 7.1 Feature Files (Gherkin)

```gherkin
Feature: Add Todo
  
  Scenario: Successfully add a todo
    Given a user is authenticated
    When they create a todo with title "Buy milk"
    Then the todo should be created
    And a TodoAdded event should be raised
```

#### 7.2 Step Definitions

```typescript
export const addTodoSteps = ({ given, when, then }: DefineSteps) => {
  let command: AddTodoCommand;
  let handler: AddTodoCommandHandler;
  let result: Either<void, Error>;
  
  given('a user is authenticated', () => {
    // Setup mocks
    mockAsyncLocalStorageGet.mockReturnValue({ userId: '123' });
  });
  
  when('they create a todo with title {string}', (title: string) => {
    command = new AddTodoCommand({ title });
    result = await handler.execute(command);
  });
  
  then('the todo should be created', () => {
    expect(result.isOk()).toBe(true);
  });
  
  then('a TodoAdded event should be raised', () => {
    expect(mockRepo.save).toHaveBeenCalled();
    const savedTodo = mockRepo.save.mock.calls[0][0];
    expect(savedTodo.domainEvents).toContainEqual(
      expect.objectContaining({
        name: 'TodoAddedDomainEvent'
      })
    );
  });
};
```

---

## 🎯 Key Learnings & Best Practices

### 1. **Dependency Flow**

```
Infrastructure → Application → Domain
     ↓              ↓             ↑
   Adapters    Use Cases     Core Logic
     ↓              ↓             
   Ports ←---------←-------------
```

**Rules:**
- ✅ Domain has ZERO dependencies
- ✅ Application depends only on Domain
- ✅ Infrastructure depends on Application & Domain
- ✅ All external dependencies go through Ports

---

### 2. **Result Pattern (Railway Oriented Programming)**

```typescript
// Either<Success, Error>
type Result<T> = Either<T, Error>;

// Success path
return ok(value);

// Error path
return fail(new DomainError());

// Usage
const result = await handler.execute(command);

if (result.isFail()) {
  // Handle error
  throw result.value;
}

const data = result.value;  // Success value
```

**Benefits:**
- ✅ Explicit error handling
- ✅ Type-safe errors
- ✅ No exceptions for flow control
- ✅ Composable

---

### 3. **Command/Query Bus Pattern**

```typescript
// Dispatch command
const result = await this.commandBus.execute(
  new AddTodoCommand({ title: 'Buy milk' })
);

// Dispatch query
const todos = await this.queryBus.execute(
  new GetTodosQuery()
);
```

**Benefits:**
- ✅ Decoupled controllers from handlers
- ✅ Easy to add middleware (logging, validation, tracing)
- ✅ Testable in isolation
- ✅ Can switch transport layer (HTTP, gRPC, Message Queue)

---

### 4. **Event Sourcing Lite**

```typescript
// Aggregate tracks events
export class TodoEntity extends Domain.Aggregate {
  public complete() {
    // Change state
    this.props.completed = true;
    
    // Record event
    this.addDomainEvent(new TodoCompletedDomainEvent({...}));
  }
}

// After save, publish events
await this.repo.save(todo);
await this.eventBus.publish(todo.domainEvents);

// Events update read model
@DomainEventHandler(TodoCompletedDomainEvent)
class UpdateTodoReadModel {
  async handle(event: TodoCompletedDomainEvent) {
    await this.readRepo.update(event.aggregateId, {
      completed: true
    });
  }
}
```

---

### 5. **Observability & Tracing**

```typescript
@Traceable({
  operation: '[Todo] AddTodoCommandHandler',
  serviceName: 'Todo',
  metrics: {
    name: 'add_todo_command',
    category: 'commandHandler'
  }
})
async execute(command: AddTodoCommand) {
  // Automatically traced with:
  // - Duration
  // - Success/failure
  // - Correlation ID
  // - Context propagation
}
```

**Stack:**
- Jaeger for distributed tracing
- Prometheus for metrics
- Grafana for visualization

---

## 🚀 Áp Dụng Vào BaultFrame

### Phase 1: Core Infrastructure ✅ (Đã Có)

- ✅ Event System (Dispatcher)
- ✅ Service Container (DI)
- ✅ Repository Pattern
- ✅ Module System

### Phase 2: Add CQRS Support ⏳

```php
// Command
interface Command {}

class AddTodoCommand implements Command {
    public function __construct(
        public readonly string $title,
        public readonly string $userId
    ) {}
}

// Command Handler
interface CommandHandler {
    public function handle(Command $command): Result;
}

class AddTodoCommandHandler implements CommandHandler {
    public function __construct(
        private TodoWriteRepository $repo,
        private EventBus $eventBus
    ) {}
    
    public function handle(Command $command): Result {
        $todo = Todo::create($command->title, $command->userId);
        $this->repo->save($todo);
        $this->eventBus->publishAll($todo->releaseEvents());
        
        return Result::ok();
    }
}

// Command Bus
interface CommandBus {
    public function execute(Command $command): Result;
}
```

### Phase 3: Domain Layer Enhancement ⏳

```php
// Aggregate Root
abstract class AggregateRoot extends Entity {
    private array $domainEvents = [];
    
    protected function addDomainEvent(DomainEvent $event): void {
        $this->domainEvents[] = $event;
    }
    
    public function releaseEvents(): array {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}

// Value Object
abstract class ValueObject {
    abstract public function equals(self $other): bool;
    abstract public function toArray(): array;
}

// Domain Event
interface DomainEvent {
    public function occurredOn(): DateTimeImmutable;
    public function aggregateId(): string;
    public function eventName(): string;
}
```

### Phase 4: Event Sourcing ⏳

```php
// Event Store
interface EventStore {
    public function append(string $aggregateId, array $events): void;
    public function getEventsFor(string $aggregateId): array;
}

// Event Sourced Aggregate
abstract class EventSourcedAggregate extends AggregateRoot {
    private int $version = 0;
    
    public static function reconstituteFrom(array $events): static {
        $instance = new static();
        foreach ($events as $event) {
            $instance->apply($event);
            $instance->version++;
        }
        return $instance;
    }
    
    abstract protected function apply(DomainEvent $event): void;
}
```

---

## 📚 Recommended Reading

1. **Domain-Driven Design** - Eric Evans
2. **Implementing Domain-Driven Design** - Vaughn Vernon
3. **Clean Architecture** - Robert C. Martin
4. **Patterns, Principles, and Practices of Domain-Driven Design** - Scott Millett

---

## 🎓 Summary

**Core Patterns Learned:**

1. ✅ **Bounded Contexts** - Modular architecture
2. ✅ **Hexagonal Architecture** - Ports & Adapters
3. ✅ **CQRS** - Separate read/write models
4. ✅ **DDD Tactical Patterns** - Entities, VOs, Aggregates, Events
5. ✅ **Event-Driven Architecture** - Async communication
6. ✅ **Result Pattern** - Explicit error handling
7. ✅ **Command/Query Bus** - Decoupled dispatching
8. ✅ **Testing** - BDD with Gherkin
9. ✅ **Observability** - Tracing & Metrics

**Apply to BaultFrame:**
- 🔄 Enhance Event System
- 🔄 Add CQRS Support
- 🔄 Implement Command/Query Bus
- 🔄 Add Result Pattern
- 🔄 Event Sourcing (optional)

---

**Analysis Date:** 2026-01-22  
**Status:** ✅ Complete  
**Next:** Apply patterns to queue system implementation
