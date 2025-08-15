# BaultPHP: Framework PHP Hiện Đại & Modular

**BaultPHP** là một framework PHP được xây dựng từ đầu với triết lý thiết kế hướng đến hiệu năng cao, cấu trúc rõ ràng và khả năng mở rộng linh hoạt. Framework lấy cảm hứng từ các tư tưởng của **Domain-Driven Design (DDD)** và kiến trúc modular, giúp việc phát triển các ứng dụng phức tạp trở nên dễ dàng quản lý và bảo trì.

## Triết lý cốt lõi

- **Modular là trên hết**: Mọi thứ trong BaultPHP đều có thể được tổ chức thành các **Module** độc lập. Mỗi module có cấu trúc thư mục riêng, service provider, routes, và migrations, cho phép các nhóm phát triển song song và tái sử dụng code hiệu quả.
- **Hiệu năng cao**: Framework được xây dựng trên nền tảng **Swoole**, một PHP extension cho phép lập trình bất đồng bộ, hiệu năng cao, giúp xử lý hàng ngàn kết nối đồng thời với độ trễ thấp.
- **Developer-friendly**: Cung cấp bộ công cụ dòng lệnh (CLI) mạnh mẽ để tự động hóa các tác vụ lặp đi lặp lại như tạo module, controller, use case, v.v., giúp lập trình viên tập trung vào logic nghiệp vụ.
- **Tự chủ và Tối giản**: Thay vì phụ thuộc vào các thư viện lớn, BaultPHP tự xây dựng các thành phần cốt lõi như ORM, Routing, và Authentication. Điều này mang lại sự kiểm soát tối đa và một codebase gọn nhẹ, dễ hiểu.

## Các tính năng nổi bật

- **Kiến trúc Modular theo DDD**: Dễ dàng tạo và quản lý các module với cấu trúc chuẩn Domain-Driven Design thông qua câu lệnh `ddd:make-module`.
- **Hệ thống Routing linh hoạt**: Hỗ trợ cả **File-based Routing** (truyền thống) và **Attribute-based Routing** (hiện đại), tự động quét và đăng ký route từ các controller trong module.
- **Dependency Injection Container**: Một DI Container đơn giản nhưng mạnh mẽ để quản lý các dependency trong toàn bộ ứng dụng.
- **ORM tùy biến**: Một lớp ORM (Object-Relational Mapping) được xây dựng riêng, hỗ trợ các thao tác cơ bản với cơ sở dữ liệu, soft deletes, và query builder.
- **Hệ thống Migration mạnh mẽ**: Quản lý schema cơ sở dữ liệu cho từng module, hỗ trợ chạy, rollback theo từng batch.
- **Xác thực (Authentication) đa cơ chế**: Hỗ trợ nhiều "guard" khác nhau, bao gồm `SessionGuard` cho web truyền thống và `JwtGuard` cho các API stateless.
- **Tích hợp Swoole**: Sẵn sàng cho môi trường production hiệu năng cao với server được quản lý qua các lệnh `serve:start` (để chạy server) và `serve:watch` (để phát triển với hot-reload).
- **Bộ công cụ CLI tiện lợi**: Bao gồm các lệnh `route:cache`, `config:cache`, và rất nhiều lệnh `make:*`, `ddd:*` để tăng tốc phát triển.
- **Xử lý lỗi tập trung**: `ExceptionHandler` trung tâm với sự hỗ trợ của `Whoops` cho môi trường debug, giúp việc gỡ lỗi trở nên trực quan.

## Kiến trúc tổng quan

### 1. Khởi động Server (Server Bootstrapping)

1.  Mọi request đều đi qua `public/index.php`, khởi tạo `Core\AppKernel`.
2.  Một đối tượng `Core\Server\SwooleServer` được tạo ra.
3.  Swoole server khởi tạo các **Worker Process**.
4.  Trong mỗi Worker Process, một instance của `Core\Application` (DI Container) được khởi tạo và bootstrap **CHỈ MỘT LẦN**. Quá trình bootstrap này sẽ đăng ký và boot tất cả các **Service Provider**.
5.  Worker process sau đó sẵn sàng nhận và xử lý nhiều request.

### 2. Luồng xử lý Request

1.  Khi có một HTTP request đến, Swoole server chuyển nó đến một Worker Process đang rảnh.
2.  Sự kiện `request` của `SwooleServer` được kích hoạt.
3.  Request của Swoole được chuyển đổi thành một PSR-7 Request.
4.  Request được xử lý bởi `Application->handle()` (thông qua `Http\Kernel`).
5.  `Router` tìm kiếm route phù hợp, thực thi Controller, và nhận về một PSR-7 Response.
6.  PSR-7 Response được chuyển đổi ngược lại thành Swoole Response và gửi về cho client.
7.  Worker được dọn dẹp (reset các stateful service) và sẵn sàng cho request tiếp theo mà không cần khởi động lại framework.

## Các thành phần chính

### Routing

BaultPHP hỗ trợ 2 cách định nghĩa route:

**a. Attribute-based (Khuyến khích)**

Định nghĩa route trực tiếp trên phương thức của controller. Tự động được quét và đăng ký khi ứng dụng khởi động.

_File: `Modules/User/Http/Controllers/UserController.php`_

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

_File: `routes/web.php`_

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

_File: `Modules/User/Infrastructure/Models/User.php`_

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

### Database Seeding

BaultPHP cung cấp một cách đơn giản để điền dữ liệu mẫu vào cơ sở dữ liệu của bạn bằng cách sử dụng các lớp "seeder". Tất cả các seeder được đặt trong thư mục `database/seeders`.

**1. Tạo một Seeder**

Tạo một file mới, ví dụ `database/seeders/UserSeeder.php`, và kế thừa từ `Core\Database\Seeder`.

```php
namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\User\Infrastructure\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);
    }
}
```

**2. Chạy Seeder**

Bạn có thể chạy các seeder bằng lệnh `db:seed`. Theo mặc định, lệnh này sẽ chạy lớp `Database\Seeders\DatabaseSeeder`, lớp này có thể được sử dụng để gọi các seeder khác.

```bash
# Chạy seeder chính (DatabaseSeeder)
php cli db:seed

# Chạy một seeder cụ thể
php cli db:seed --class=UserSeeder
```

### Console Commands

BaultPHP cung cấp một bộ lệnh CLI phong phú để hỗ trợ phát triển.

```bash
# Chạy server Swoole (dùng cho cả development và production trong Docker)
php cli serve:start

# Chạy server với chế độ theo dõi file thay đổi (hot-reload)
php cli serve:watch

# Tạo một module mới với cấu trúc DDD
php cli ddd:make-module Product

# Tạo một UseCase trong module User
php cli ddd:make-usecase User CreateNewUser

# Tạo cache cho routes để tăng tốc
php cli route:cache

# Tạo cache cho service providers
php cli config:cache
```
