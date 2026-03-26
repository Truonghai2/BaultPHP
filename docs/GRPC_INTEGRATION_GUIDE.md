# gRPC & Protocol Buffers Integration Guide

## Tổng quan

gRPC là một high-performance RPC framework sử dụng Protocol Buffers cho serialization. Framework đã tích hợp đầy đủ support cho gRPC client và server.

## Cài đặt

### 1. PHP gRPC Extension

```bash
# Install gRPC extension
pecl install grpc

# Enable extension
echo "extension=grpc.so" >> php.ini
```

### 2. Protocol Buffers Compiler

```bash
# macOS
brew install protobuf

# Linux
apt-get install protobuf-compiler

# Or download from https://github.com/protocolbuffers/protobuf/releases
```

### 3. gRPC PHP Plugin

```bash
# Install grpc_php_plugin
# Usually comes with grpc extension or can be built from source
```

### 4. Composer Dependencies

Dependencies đã được thêm vào `composer.json`:
- `grpc/grpc`: gRPC PHP library
- `google/protobuf`: Protocol Buffers PHP library

```bash
composer install
```

## Cấu hình

### Environment Variables (.env)

```env
# gRPC Client
GRPC_CLIENT_TIMEOUT=30
GRPC_CLIENT_RETRY=false

# gRPC Server
GRPC_SERVER_HOST=0.0.0.0
GRPC_SERVER_PORT=50051
GRPC_SERVER_SECURE=false
GRPC_SERVER_CERT=
GRPC_SERVER_KEY=

# Protocol Buffers
PROTOC_PATH=/usr/local/bin/protoc
GRPC_PHP_PLUGIN_PATH=/usr/local/bin/grpc_php_plugin
PROTO_DIRECTORY=proto
GRPC_GENERATED_DIRECTORY=src/Grpc/Generated
```

### Service Configuration

Cấu hình services trong `config/grpc.php`:

```php
'services' => [
    'user.UserService' => [
        'host' => env('GRPC_USER_SERVICE_HOST', 'localhost'),
        'port' => env('GRPC_USER_SERVICE_PORT', 50051),
        'stub_class' => \App\Grpc\Services\User\UserServiceClient::class,
        'secure' => env('GRPC_USER_SERVICE_SECURE', false),
    ],
],
```

## Sử dụng

### 1. Define Protocol Buffers

Tạo file `.proto` trong thư mục `proto/`:

```protobuf
// proto/user.proto
syntax = "proto3";

package user;

service UserService {
    rpc GetUser (GetUserRequest) returns (UserResponse);
}

message GetUserRequest {
    int32 user_id = 1;
}

message UserResponse {
    int32 id = 1;
    string name = 2;
    string email = 3;
}
```

### 2. Compile Protocol Buffers

```bash
# Compile single file
php cli grpc:compile proto/user.proto

# Compile directory
php cli grpc:compile proto/

# Custom output directory
php cli grpc:compile proto/user.proto --output=src/Grpc/Generated
```

### 3. Use gRPC Client

```php
use App\Grpc\Generated\User\GetUserRequest;
use App\Grpc\Generated\User\UserResponse;

// Method 1: Using helper function
$request = new GetUserRequest();
$request->setUserId(1);
$response = grpc('user.UserService', 'GetUser', $request);

// Method 2: Using client directly
$client = grpc_client();
$response = $client->call('user.UserService', 'GetUser', $request);

// Method 3: Using service manager
$manager = grpc();
$response = $manager->call('user.UserService', 'GetUser', $request);
```

### 4. Create gRPC Server

```php
// In ServiceProvider
use App\Grpc\Services\User\UserServiceImpl;

public function boot(): void
{
    $server = grpc_server();
    $server->registerService('user.UserService', new UserServiceImpl());
}

// Start server
php cli grpc:serve --host=0.0.0.0 --port=50051
```

## Examples

### Example 1: User Service Client

```php
namespace App\Services;

use App\Grpc\Generated\User\GetUserRequest;
use App\Grpc\Generated\User\UserResponse;
use Core\RPC\GrpcException;

class UserServiceClient
{
    public function getUser(int $userId): ?array
    {
        try {
            $request = new GetUserRequest();
            $request->setUserId($userId);
            
            /** @var UserResponse $response */
            $response = grpc('user.UserService', 'GetUser', $request);
            
            return [
                'id' => $response->getId(),
                'name' => $response->getName(),
                'email' => $response->getEmail(),
            ];
        } catch (GrpcException $e) {
            if ($e->getGrpcCode() === \Grpc\STATUS_NOT_FOUND) {
                return null;
            }
            throw $e;
        }
    }
}
```

### Example 2: User Service Implementation

```php
namespace App\Grpc\Services\User;

use App\Grpc\Generated\User\GetUserRequest;
use App\Grpc\Generated\User\UserResponse;
use Modules\User\Infrastructure\Models\User;

class UserServiceImpl
{
    public function GetUser(GetUserRequest $request, \Grpc\ServerCallWriter $writer): void
    {
        $user = User::find($request->getUserId());
        
        if (!$user) {
            $writer->finish(null, ['code' => \Grpc\STATUS_NOT_FOUND]);
            return;
        }
        
        $response = new UserResponse();
        $response->setId($user->id);
        $response->setName($user->name);
        $response->setEmail($user->email);
        
        $writer->finish($response);
    }
}
```

### Example 3: Streaming Response

```protobuf
// proto/stream.proto
service StreamService {
    rpc StreamData (StreamRequest) returns (stream StreamResponse);
}
```

```php
public function StreamData(StreamRequest $request, \Grpc\ServerCallWriter $writer): void
{
    $data = $this->getData($request);
    
    foreach ($data as $item) {
        $response = new StreamResponse();
        $response->setData($item);
        $writer->write($response);
    }
    
    $writer->finish();
}
```

## Performance Benefits

### Comparison với REST API

| Metric | REST (JSON) | gRPC (Protobuf) |
|--------|-------------|-----------------|
| **Payload Size** | ~100% | ~30-50% |
| **Serialization** | ~5ms | ~1ms |
| **Latency** | ~50ms | ~10ms |
| **Throughput** | 1000 req/s | 5000+ req/s |

### Use Cases

✅ **Nên dùng gRPC cho:**
- Inter-service communication
- High-frequency API calls
- Real-time streaming
- Microservices architecture
- Mobile apps (smaller payload)

❌ **Không nên dùng gRPC cho:**
- Public REST APIs
- Browser clients (cần gRPC-Web)
- Simple CRUD operations
- Legacy systems

## Advanced Features

### 1. Connection Pooling

gRPC client tự động manage connection pooling:

```php
// Connections are reused automatically
$client = grpc_client();
$client->call('service', 'method1', $request1); // Creates connection
$client->call('service', 'method2', $request2); // Reuses connection
```

### 2. Retry Logic

Enable automatic retry:

```php
// In config/grpc.php
'retry' => true,

// Or per-call
grpc('service', 'method', $request, ['retry' => true]);
```

### 3. Timeout Configuration

```php
// Global timeout
'client' => [
    'timeout' => 30, // seconds
],

// Per-call timeout
grpc('service', 'method', $request, [
    'timeout' => 60 * 1000000, // microseconds
]);
```

### 4. Secure Connections (TLS)

```php
// Client
'services' => [
    'secure.Service' => [
        'host' => 'secure.example.com',
        'port' => 443,
        'secure' => true,
        'root_cert' => '/path/to/ca-cert.pem',
    ],
],

// Server
'server' => [
    'secure' => true,
    'cert' => '/path/to/server-cert.pem',
    'key' => '/path/to/server-key.pem',
],
```

## Docker Integration

### Dockerfile Updates

```dockerfile
# Install protobuf compiler
RUN apt-get update && apt-get install -y protobuf-compiler

# Install gRPC PHP extension
RUN pecl install grpc && docker-php-ext-enable grpc
```

### docker-compose.yml

```yaml
services:
  app:
    environment:
      - GRPC_SERVER_PORT=50051
      - GRPC_USER_SERVICE_HOST=user-service
      - GRPC_USER_SERVICE_PORT=50051
```

## Troubleshooting

### gRPC Extension Not Found

**Error:** `Class 'Grpc\BaseStub' not found`

**Solution:**
```bash
pecl install grpc
docker-php-ext-enable grpc
```

### protoc Not Found

**Error:** `protoc compiler is not available`

**Solution:**
```bash
# Install protoc
brew install protobuf  # macOS
apt-get install protobuf-compiler  # Linux

# Or set path in .env
PROTOC_PATH=/usr/local/bin/protoc
```

### Connection Refused

**Error:** `Connection refused`

**Solution:**
1. Check service is running
2. Verify host and port
3. Check firewall rules
4. Verify network connectivity

### Compilation Errors

**Error:** `Proto compilation failed`

**Solution:**
1. Check .proto syntax
2. Verify import paths
3. Ensure grpc_php_plugin is available
4. Check file permissions

## Best Practices

### 1. Service Naming

Use full service names:
```protobuf
package user;
service UserService { ... }
// Full name: user.UserService
```

### 2. Error Handling

```php
try {
    $response = grpc('service', 'method', $request);
} catch (GrpcException $e) {
    if ($e->isRetryable()) {
        // Retry logic
    }
    // Handle error
}
```

### 3. Message Design

- Use appropriate field numbers
- Don't reuse field numbers
- Use `repeated` for arrays
- Use `oneof` for optional fields

### 4. Versioning

```protobuf
// Use package versioning
package user.v1;
package user.v2;
```

## Resources

- [gRPC Official Docs](https://grpc.io/docs/)
- [Protocol Buffers Guide](https://protobuf.dev/)
- [gRPC PHP Examples](https://github.com/grpc/grpc/tree/master/src/php)
- [Best Practices](https://grpc.io/docs/guides/best-practices/)
