# GraphQL Usage Guide

## Tổng quan

Hệ thống GraphQL đã được hoàn thiện và sẵn sàng sử dụng. Framework sử dụng **GraphQLite** để tự động generate GraphQL schema từ PHP code.

## Cấu trúc

### 1. Endpoints

- **POST `/graphql`** - Xử lý GraphQL queries và mutations
- **GET `/graphql`** - GraphiQL playground để test queries

### 2. Cấu hình

File cấu hình: `config/graphqlite.php` và `config/graphql.php`

```php
// config/graphqlite.php
'namespaces' => [
    'controllers' => ['Modules\\'], // Nơi tìm Query/Mutation
    'types' => ['Modules\\'],      // Nơi tìm Type definitions
],
```

## Cách sử dụng

### 1. Tạo GraphQL Query

Tạo file trong `Modules/{ModuleName}/GraphQL/Queries/`:

```php
<?php

namespace Modules\User\GraphQL\Queries;

use Modules\User\GraphQL\Types\UserType;
use Modules\User\Infrastructure\Models\User;
use TheCodingMachine\GraphQLite\Annotations\Query;

class UserQuery
{
    #[Query]
    public function user(int $id): ?UserType
    {
        $user = User::find($id);
        return $user ? UserType::fromModel($user) : null;
    }

    #[Query]
    public function users(): array
    {
        return User::all()->map(fn($u) => UserType::fromModel($u))->toArray();
    }
}
```

### 2. Tạo GraphQL Type

Tạo file trong `Modules/{ModuleName}/GraphQL/Types/`:

```php
<?php

namespace Modules\User\GraphQL\Types;

use Modules\User\Infrastructure\Models\User;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class UserType
{
    private function __construct(
        #[Field]
        public readonly int $id,
        
        #[Field]
        public readonly string $name,
        
        #[Field]
        public readonly string $email,
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
```

### 3. Tạo GraphQL Mutation

Tạo file trong `Modules/{ModuleName}/GraphQL/Mutations/`:

```php
<?php

namespace Modules\User\GraphQL\Mutations;

use Core\CQRS\Command\CommandBus;
use Modules\User\GraphQL\Types\UserType;
use Modules\User\Infrastructure\Models\User;
use TheCodingMachine\GraphQLite\Annotations\Mutation;

class UserMutation
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[Mutation]
    public function updateUserProfile(
        int $id,
        ?string $name = null,
        ?string $email = null,
    ): UserType {
        // Use CommandBus để dispatch command
        // $command = new UpdateUserProfileCommand($id, $name, $email);
        // $this->commandBus->dispatch($command);
        
        $user = User::findOrFail($id);
        return UserType::fromModel($user);
    }
}
```

## Ví dụ Query

### Query đơn giản

```graphql
query {
  user(id: 1) {
    id
    name
    email
  }
}
```

### Query với variables

```graphql
query GetUser($userId: Int!) {
  user(id: $userId) {
    id
    name
    email
  }
}
```

Variables:
```json
{
  "userId": 1
}
```

### Mutation

```graphql
mutation {
  updateUserProfile(
    id: 1
    name: "New Name"
    email: "new@example.com"
  ) {
    id
    name
    email
  }
}
```

## Testing

### Sử dụng GraphiQL

1. Mở trình duyệt và truy cập: `http://localhost:9501/graphql`
2. GraphiQL interface sẽ hiển thị
3. Nhập query và click "Execute Query"

### Sử dụng cURL

```bash
curl -X POST http://localhost:9501/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query { users { id name email } }"
  }'
```

## Tích hợp với CQRS

GraphQL resolvers có thể sử dụng CommandBus và QueryBus:

```php
use Core\CQRS\Command\CommandBus;
use Core\CQRS\Query\QueryBus;

class UserQuery
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Query]
    public function user(int $id): ?UserType
    {
        // Sử dụng QueryBus
        $query = new GetUserByIdQuery($id);
        $userDto = $this->queryBus->dispatch($query);
        
        // Convert DTO to UserType
        // ...
    }
}
```

## Authentication

Để thêm authentication vào GraphQL context, cập nhật `GraphQLController::createContext()`:

```php
protected function createContext(ServerRequestInterface $request): Context
{
    $context = new Context();
    $context->setRequest($request);
    
    // Add authenticated user
    $user = $request->getAttribute('user');
    if ($user) {
        $context->setUser($user);
    }
    
    return $context;
}
```

## DataLoader

DataLoader đã được đăng ký và có thể sử dụng để tránh N+1 queries:

```php
use Core\GraphQL\DataLoader;

class UserQuery
{
    public function __construct(
        private readonly DataLoader $dataLoader,
    ) {
    }

    #[Query]
    public function user(int $id): ?UserType
    {
        $loader = $this->dataLoader->createLoader('user', function (array $ids) {
            $users = User::whereIn('id', $ids)->get();
            $result = [];
            foreach ($users as $user) {
                $result[$user->id] = UserType::fromModel($user);
            }
            return $result;
        });
        
        return $loader($id);
    }
}
```

## Federation (Advanced)

Federation Gateway đã được implement nhưng cần cấu hình thêm:

```php
// Trong service provider hoặc bootstrap
$gateway = app(\Core\GraphQL\FederationGateway::class);
$gateway->registerSubgraph('user_service', 'http://user-service/graphql');
$gateway->buildFederatedSchema();
```

## Troubleshooting

### Schema không được generate

- Kiểm tra namespaces trong `config/graphqlite.php`
- Đảm bảo classes có đúng attributes (`#[Query]`, `#[Mutation]`, `#[Type]`)
- Clear cache: `php cli cache:clear`

### Lỗi "No handler found"

- Đảm bảo Query/Mutation classes nằm trong namespace được cấu hình
- Kiểm tra autoloading: `composer dump-autoload`

### Lỗi type mismatch

- Kiểm tra return types của resolvers
- Đảm bảo Type classes có đúng `#[Type]` attribute
- Kiểm tra Field types trong Type classes

## Best Practices

1. **Sử dụng CQRS**: GraphQL resolvers nên sử dụng QueryBus/CommandBus thay vì truy cập trực tiếp vào models
2. **Type Safety**: Luôn định nghĩa return types rõ ràng
3. **Error Handling**: Sử dụng exceptions để xử lý lỗi, GraphQL sẽ tự động format
4. **DataLoader**: Sử dụng DataLoader cho các relationships để tránh N+1 queries
5. **Authorization**: Implement field-level authorization khi cần

## Tài liệu tham khảo

- [GraphQLite Documentation](https://graphqlite.thecodingmachine.io/)
- [GraphQL Specification](https://graphql.org/learn/)
- [Apollo Federation](https://www.apollographql.com/docs/federation/)
