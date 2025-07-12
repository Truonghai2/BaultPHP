# BaultPHP: Framework PHP Hiện Đại & Modular

**BaultPHP** là một framework PHP được xây dựng từ đầu với triết lý thiết kế hướng đến hiệu năng cao, cấu trúc rõ ràng và khả năng mở rộng linh hoạt. Framework lấy cảm hứng từ các tư tưởng của **Domain-Driven Design (DDD)** và kiến trúc modular, giúp việc phát triển các ứng dụng phức tạp trở nên dễ dàng quản lý và bảo trì.

## Triết lý cốt lõi

*   **Modular là trên hết**: Mọi thứ trong BaultPHP đều có thể được tổ chức thành các **Module** độc lập. Mỗi module có cấu trúc thư mục riêng, service provider, routes, và migrations, cho phép các nhóm phát triển song song và tái sử dụng code hiệu quả.
*   **Hiệu năng cao**: Framework được thiết kế để tích hợp liền mạch với **RoadRunner**, một application server hiệu năng cao cho PHP, giúp giảm thiểu thời gian khởi động và tối ưu hóa việc sử dụng bộ nhớ trong môi trường production.
*   **Developer-friendly**: Cung cấp bộ công cụ dòng lệnh (CLI) mạnh mẽ để tự động hóa các tác vụ lặp đi lặp lại như tạo module, controller, use case, v.v., giúp lập trình viên tập trung vào logic nghiệp vụ.
*   **Tự chủ và Tối giản**: Thay vì phụ thuộc vào các thư viện lớn, BaultPHP tự xây dựng các thành phần cốt lõi như ORM, Routing, và Authentication. Điều này mang lại sự kiểm soát tối đa và một codebase gọn nhẹ, dễ hiểu.

## Các tính năng nổi bật

*   **Kiến trúc Modular theo DDD**: Dễ dàng tạo và quản lý các module với cấu trúc chuẩn Domain-Driven Design thông qua câu lệnh `ddd:make-module`.
*   **Hệ thống Routing linh hoạt**: Hỗ trợ cả **File-based Routing** (truyền thống) và **Attribute-based Routing** (hiện đại), tự động quét và đăng ký route từ các controller trong module.
*   **Dependency Injection Container**: Một DI Container đơn giản nhưng mạnh mẽ để quản lý các dependency trong toàn bộ ứng dụng.
*   **ORM tùy biến**: Một lớp ORM (Object-Relational Mapping) được xây dựng riêng, hỗ trợ các thao tác cơ bản với cơ sở dữ liệu, soft deletes, và query builder.
*   **Hệ thống Migration mạnh mẽ**: Quản lý schema cơ sở dữ liệu cho từng module, hỗ trợ chạy, rollback theo từng batch.
*   **Xác thực (Authentication) đa cơ chế**: Hỗ trợ nhiều "guard" khác nhau, bao gồm `SessionGuard` cho web truyền thống và `JwtGuard` cho các API stateless.
*   **Tích hợp RoadRunner**: Sẵn sàng cho môi trường production hiệu năng cao với file `worker.php` được cấu hình sẵn.
*   **Bộ công cụ CLI tiện lợi**: Bao gồm các lệnh `serve`, `route:cache`, `config:cache`, và rất nhiều lệnh `make:*`, `ddd:*` để tăng tốc phát triển.
*   **Xử lý lỗi tập trung**: `ExceptionHandler` trung tâm với sự hỗ trợ của `Whoops` cho môi trường debug, giúp việc gỡ lỗi trở nên trực quan.

## Kiến trúc tổng quan

### 1. Khởi động ứng dụng (Application Bootstrapping)

1.  Mọi request đều đi qua `public/index.php`, khởi tạo `Core\AppKernel`.
2.  `AppKernel` tạo ra một instance của `Core\Application` (DI Container).
3.  Kernel đăng ký các **Service Provider** cốt lõi và các provider từ những module được kích hoạt (dựa trên file `module.json`).
4.  Sau khi tất cả provider được `register`, Kernel sẽ gọi phương thức `boot` trên từng provider.
5.  Cuối cùng, request được chuyển đến `Http\Kernel` để xử lý.

### 2. Luồng xử lý Request

1.  `Http\Kernel` nhận `Request`.
2.  `Router` tìm kiếm route phù hợp với URI và phương thức HTTP.
3.  Nếu tìm thấy, router sẽ thực thi handler của route đó (thường là một phương thức trong Controller).
4.  Controller xử lý logic, có thể tương tác với các Use Case, Model, và các service khác.
5.  Controller trả về một đối tượng `Response`.
6.  `Http\Kernel` gửi `Response` về cho client.

## Các thành phần chính

### Routing

BaultPHP hỗ trợ 2 cách định nghĩa route:

**a. Attribute-based (Khuyến khích)**

Định nghĩa route trực tiếp trên phương thức của controller. Tự động được quét và đăng ký khi ứng dụng khởi động.

*File: `Modules/User/Http/Controllers/UserController.php`*
```php
<?php

namespace Modules\User\Http\Controllers;

use Core\Routing\Attributes\Route;
use Http\Response;

class UserController
{
    #[Route('/api/users', method: 'POST')]
    public function store(StoreUserRequest $request): Response
    {
        // ... logic
        return (new Response())->json(['message' => 'User created']);
    }
}
```

**b. File-based**

Định nghĩa route trong các file PHP, tương tự như Laravel.

*File: `routes/web.php`*
```php
<?php

use Core\Routing\Router;

return function (Router $router) {
    $router->get('/', function () {
        return 'Chào mừng đến với BaultPHP!';
    });
};
```

### Database & ORM

Framework cung cấp một ORM đơn giản để làm việc với CSDL.

*File: `Modules/User/Infrastructure/Models/User.php`*
```php
<?php

namespace Modules\User\Infrastructure\Models;

use Core\ORM\Model;
use Core\Contracts\Auth\Authenticatable;

class User extends Model implements Authenticatable
{
    protected static string $table = 'users';
    // ...
}
```

**Cách sử dụng:**
```php
// Tìm user theo ID
$user = User::find(1);

// Lấy tất cả user
$users = User::all();

// Tạo user mới
$newUser = new User();
$newUser->name = 'Bault Frame';
$newUser->email = 'contact@bault.dev';
$newUser->save();
```

### Migrations

Quản lý thay đổi CSDL một cách có hệ thống.

1.  Tạo file migration trong thư mục `Infrastructure/Migrations` của module.
2.  Chạy lệnh để áp dụng các migration mới:
    ```bash
    php cli ddd:migrate
    ```
3.  Các tùy chọn khác:
    ```bash
    php cli ddd:migrate --rollback  # Quay lại batch cuối cùng
    php cli ddd:migrate --status    # Xem trạng thái các migration
    php cli ddd:migrate --refresh   # Rollback tất cả và chạy lại từ đầu
    ```

### Console Commands

BaultPHP cung cấp một bộ lệnh CLI phong phú để hỗ trợ phát triển.

```bash
# Chạy server phát triển local
php cli serve

# Tạo một module mới với cấu trúc DDD
php cli ddd:make-module Product

# Tạo một UseCase trong module User
php cli ddd:make-usecase User CreateNewUser

# Tạo cache cho routes để tăng tốc
php cli route:cache

# Tạo cache cho service providers
php cli config:cache
```