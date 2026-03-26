# gRPC Quick Start Guide

## ✅ Đã tích hợp gRPC & Protocol Buffers

Framework đã được tích hợp đầy đủ gRPC client và server support.

## 🚀 Quick Start

### 1. Install Dependencies

```bash
# Install gRPC PHP extension
pecl install grpc

# Install Protocol Buffers compiler
brew install protobuf  # macOS
# or
apt-get install protobuf-compiler  # Linux

# Install Composer dependencies
composer install
```

### 2. Define Protocol Buffers

Tạo file `.proto` trong `proto/`:

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

### 3. Compile to PHP

```bash
php cli grpc:compile proto/user.proto
```

### 4. Use gRPC Client

```php
use App\Grpc\Generated\User\GetUserRequest;

$request = new GetUserRequest();
$request->setUserId(1);

$response = grpc('user.UserService', 'GetUser', $request);
echo $response->getName();
```

### 5. Create gRPC Server

```php
// Register service
grpc_server()->registerService('user.UserService', new UserServiceImpl());

// Start server
php cli grpc:serve
```

## 📝 Configuration

Thêm vào `.env`:

```env
GRPC_SERVER_HOST=0.0.0.0
GRPC_SERVER_PORT=50051
GRPC_CLIENT_TIMEOUT=30
```

## 🐳 Docker

gRPC đã được tích hợp vào Docker:

```bash
docker-compose build app
docker-compose up -d
```

gRPC extension và protoc compiler sẽ được tự động cài đặt.

## 📚 Documentation

Xem chi tiết tại: `docs/GRPC_INTEGRATION_GUIDE.md`
