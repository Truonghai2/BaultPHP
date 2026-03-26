# gRPC Examples

## Example 1: User Service

### 1. Define Protocol Buffer

```protobuf
// proto/user.proto
syntax = "proto3";

package user;

service UserService {
    rpc GetUser (GetUserRequest) returns (UserResponse);
    rpc CreateUser (CreateUserRequest) returns (UserResponse);
    rpc ListUsers (ListUsersRequest) returns (ListUsersResponse);
}

message GetUserRequest {
    int32 user_id = 1;
}

message CreateUserRequest {
    string name = 1;
    string email = 2;
    string password = 3;
}

message ListUsersRequest {
    int32 page = 1;
    int32 per_page = 2;
}

message UserResponse {
    int32 id = 1;
    string name = 2;
    string email = 3;
    string created_at = 4;
}

message ListUsersResponse {
    repeated UserResponse users = 1;
    int32 total = 2;
}
```

### 2. Compile

```bash
php cli grpc:compile proto/user.proto
```

### 3. Implement Service

```php
// src/Grpc/Services/User/UserServiceImpl.php
namespace App\Grpc\Services\User;

use App\Grpc\Generated\User\GetUserRequest;
use App\Grpc\Generated\User\UserResponse;
use App\Grpc\Generated\User\CreateUserRequest;
use App\Grpc\Generated\User\ListUsersRequest;
use App\Grpc\Generated\User\ListUsersResponse;
use Core\RPC\ServiceHandler;
use Modules\User\Infrastructure\Models\User;

class UserServiceImpl extends ServiceHandler
{
    public function GetUser(GetUserRequest $request, \Grpc\ServerCallWriter $writer): void
    {
        $user = User::find($request->getUserId());
        
        if (!$user) {
            $this->sendNotFound("User not found", $writer);
            return;
        }
        
        $response = new UserResponse();
        $response->setId($user->id);
        $response->setName($user->name);
        $response->setEmail($user->email);
        $response->setCreatedAt($user->created_at->toIso8601String());
        
        $this->sendResponse($response, $writer);
    }
    
    public function CreateUser(CreateUserRequest $request, \Grpc\ServerCallWriter $writer): void
    {
        $user = User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            'password' => bcrypt($request->getPassword()),
        ]);
        
        $response = new UserResponse();
        $response->setId($user->id);
        $response->setName($user->name);
        $response->setEmail($user->email);
        $response->setCreatedAt($user->created_at->toIso8601String());
        
        $this->sendResponse($response, $writer);
    }
    
    public function ListUsers(ListUsersRequest $request, \Grpc\ServerCallWriter $writer): void
    {
        $page = $request->getPage() ?: 1;
        $perPage = $request->getPerPage() ?: 20;
        
        $users = User::paginate($perPage, ['*'], 'page', $page);
        
        $response = new ListUsersResponse();
        $response->setTotal($users->total());
        
        foreach ($users->items() as $user) {
            $userResponse = new UserResponse();
            $userResponse->setId($user->id);
            $userResponse->setName($user->name);
            $userResponse->setEmail($user->email);
            $userResponse->setCreatedAt($user->created_at->toIso8601String());
            
            $response->getUsers()[] = $userResponse;
        }
        
        $this->sendResponse($response, $writer);
    }
}
```

### 4. Register Service

```php
// In ServiceProvider
use App\Grpc\Services\User\UserServiceImpl;

public function boot(): void
{
    grpc_server()->registerService('user.UserService', new UserServiceImpl());
}
```

### 5. Use Client

```php
use App\Grpc\Generated\User\GetUserRequest;
use App\Grpc\Generated\User\UserResponse;

$request = new GetUserRequest();
$request->setUserId(1);

/** @var UserResponse $response */
$response = grpc('user.UserService', 'GetUser', $request);

echo $response->getName();
echo $response->getEmail();
```

## Example 2: Streaming Service

### Proto Definition

```protobuf
service StreamService {
    rpc StreamData (StreamRequest) returns (stream StreamResponse);
    rpc BidirectionalStream (stream StreamRequest) returns (stream StreamResponse);
}
```

### Implementation

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

## Example 3: Error Handling

```php
try {
    $response = grpc('user.UserService', 'GetUser', $request);
} catch (\Core\RPC\GrpcException $e) {
    match ($e->getGrpcCode()) {
        \Grpc\STATUS_NOT_FOUND => // Handle not found
        \Grpc\STATUS_INVALID_ARGUMENT => // Handle invalid argument
        \Grpc\STATUS_UNAVAILABLE => // Handle service unavailable
        default => // Handle other errors
    };
}
```
