# Dependency Injection Container trong BaultPHP

## Giới thiệu: DI Container là gì?

Dependency Injection (DI) Container (còn gọi là IoC Container - Inversion of Control) là một trong những thành phần cốt lõi và mạnh mẽ nhất của các framework hiện đại, bao gồm cả BaultPHP. Về cơ bản, nó là một đối tượng có chức năng quản lý việc khởi tạo và các phụ thuộc (dependencies) của các đối tượng (class) khác trong ứng dụng của bạn.

Thay vì bạn phải tự tay tạo một đối tượng và tất cả các đối tượng mà nó cần bên trong constructor:

```php
// Cách làm thủ công (Không dùng DI)
class MyController {
    private $service;
    public function __construct() {
        // Tự tay khởi tạo, rất cứng nhắc và khó thay đổi
        $config = ['key' => 'value'];
        $this->service = new MyService($config);
    }
}
```

Bạn chỉ cần "khai báo" những gì bạn cần, và DI Container sẽ tự động cung cấp chúng cho bạn:

```php
// Cách làm với DI
class MyController {
    // Chỉ cần khai báo service bạn cần trong constructor
    public function __construct(private MyService $service) {
        // Container sẽ tự động inject một instance của MyService vào đây
    }
}
```

Việc này mang lại lợi ích to lớn:

- **Loose Coupling (Giảm sự phụ thuộc):** Các class không cần biết cách tạo ra các phụ thuộc của chúng.
- **Dễ dàng cấu hình:** Cấu hình cho các service được tập trung ở một nơi duy nhất.
- **Tăng khả năng Test:** Dễ dàng thay thế các service thật bằng các đối tượng giả (mock) khi viết unit test.

## Cách hoạt động trong BaultPHP

Trong BaultPHP, trái tim của hệ thống DI là class `Core\Application`. Class này hoạt động như một "sổ đăng ký" toàn cục, nơi bạn có thể "dạy" cho nó cách tạo ra các service khác nhau. Quá trình này gồm hai bước chính: **Đăng ký (Binding)** và **Giải quyết (Resolving)**.

### 1. Đăng ký Dịch vụ (Binding Services) với Service Provider

**Service Provider** là nơi tập trung để đăng ký tất cả các dịch vụ của bạn vào DI Container. BaultPHP sẽ tự động load tất cả các Service Provider được định nghĩa trong ứng dụng và các module.

Việc đăng ký được thực hiện bên trong phương thức `register()` của một Service Provider.

**Ví dụ: Đăng ký `CentrifugoAPIService` trong `AppServiceProvider.php`**

```php
// e:\temp\BaultPHP\src\Providers\AppServiceProvider.php

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ...

        // Đăng ký CentrifugoAPIService như một singleton.
        $this->app->singleton(CentrifugoAPIService::class, function () {
            $apiUrl = $_ENV['CENTRIFUGO_API_URL'] ?? 'http://127.0.0.1:8000';
            $apiKey = $_ENV['CENTRIFUGO_API_KEY'] ?? null;

            if (is_null($apiKey)) {
                throw new \InvalidArgumentException('...');
            }

            // Trả về một instance mới của service
            return new CentrifugoAPIService($apiUrl, $apiKey);
        });
    }
}
```

Trong ví dụ trên:

- `$this->app` chính là instance của `Core\Application` (DI Container).
- `singleton(CentrifugoAPIService::class, ...)` nói với container rằng: "Khi ai đó yêu cầu một `CentrifugoAPIService`, hãy thực thi hàm closure này. Nhưng chỉ thực thi lần đầu tiên thôi, những lần sau hãy trả về chính instance đã được tạo ra trước đó".
- Toàn bộ logic khởi tạo và cấu hình (lấy URL, API key từ `.env`) được gói gọn tại đây.

### 2. Giải quyết Phụ thuộc (Resolving Dependencies)

Đây là quá trình container "cung cấp" các đối tượng đã được đăng ký. BaultPHP chủ yếu sử dụng **Constructor Injection** một cách tự động.

**Ví dụ: Inject `CentrifugoAPIService` vào `NotificationController.php`**

```php
// e:\temp\BaultPHP\src\Http\Controllers\Admin\NotificationController.php

class NotificationController
{
    // BaultPHP sẽ tự động "nhìn" vào đây
    public function __construct(private CentrifugoAPIService $centrifugo)
    {
        // Container sẽ tự động tìm và inject instance của CentrifugoAPIService
        // đã được đăng ký ở AppServiceProvider vào biến $centrifugo.
    }

    public function sendToUser(...)
    {
        // Giờ bạn có thể sử dụng service một cách thoải mái
        $this->centrifugo->publish(...);
    }
}
```

**Luồng hoạt động diễn ra như sau:**

1.  Một request đến route `/api/admin/notifications/user/{id}`.
2.  Router của BaultPHP xác định rằng nó cần phải tạo một instance của `NotificationController` để xử lý request.
3.  Trước khi tạo, nó dùng Reflection để "đọc" constructor của `NotificationController`.
4.  Nó thấy rằng constructor yêu cầu một tham số có type-hint là `CentrifugoAPIService`.
5.  Nó hỏi DI Container (`$app`): "Làm thế nào để tạo một `CentrifugoAPIService`?".
6.  Container trả lời: "À, tôi đã được dạy cách tạo nó trong `AppServiceProvider`. Đây là instance singleton của nó."
7.  Container trả về instance của `CentrifugoAPIService`.
8.  Framework inject instance đó vào constructor của `NotificationController` và hoàn tất việc khởi tạo controller.

## Kết luận

### `bind` vs `singleton`: Sự khác biệt chính

Trong BaultPHP, cả hai phương thức `bind` và `singleton` đều dùng để "dạy" cho container cách tạo ra một đối tượng. Sự khác biệt cốt lõi nằm ở **vòng đời (lifecycle)** của đối tượng được tạo ra.

#### `bind` (Transient Binding)

Khi bạn sử dụng `bind`, bạn đang nói với container: "Mỗi khi có ai đó yêu cầu service này, hãy tạo cho họ một instance hoàn toàn mới".

```php
// Trong một ServiceProvider
$this->app->bind(ReportGenerator::class, function() {
    return new ReportGenerator(new TemporaryFileStorage());
});

// Ở một nơi khác trong ứng dụng
$report1 = $app->make(ReportGenerator::class); // Tạo instance A
$report2 = $app->make(ReportGenerator::class); // Tạo instance B

// $report1 và $report2 là hai đối tượng hoàn toàn khác nhau.
```

- **Khi nào dùng `bind`?** Khi bạn cần một đối tượng "sạch" (fresh state) mỗi lần sử dụng. Ví dụ: một class tạo báo cáo, một đối tượng Data Transfer Object (DTO), hoặc bất kỳ class nào có trạng thái nội tại (internal state) mà bạn không muốn chia sẻ giữa các phần khác nhau của ứng dụng.

#### `singleton` (Shared Binding)

Khi bạn sử dụng `singleton`, bạn đang nói với container: "Hãy tạo một instance của service này trong lần đầu tiên nó được yêu cầu. Sau đó, với tất cả các yêu cầu tiếp theo, hãy trả về chính xác instance đã được tạo đó".

```php
// Trong một ServiceProvider
$this->app->singleton(DatabaseConnection::class, function() {
    // Logic kết nối CSDL tốn kém chỉ chạy một lần
    return new DatabaseConnection($_ENV['DB_DSN']);
});

// Ở một nơi khác trong ứng dụng
$connection1 = $app->make(DatabaseConnection::class); // Tạo instance A và lưu lại
$connection2 = $app->make(DatabaseConnection::class); // Trả về instance A đã được lưu

// $connection1 và $connection2 là cùng một đối tượng.
```

- **Khi nào dùng `singleton`?** Đây là trường hợp phổ biến nhất. Dùng cho các service không có trạng thái hoặc có trạng thái cần được chia sẻ toàn cục, và việc khởi tạo chúng tốn kém tài nguyên. Ví dụ: Kết nối CSDL, client gọi API bên ngoài (`CentrifugoAPIService`), service quản lý cache, service quản lý config. Việc này giúp tiết kiệm bộ nhớ và thời gian xử lý.

---

Hệ thống Dependency Injection của BaultPHP, tuy đơn giản, nhưng là một công cụ cực kỳ hiệu quả. Bằng cách tập trung việc khởi tạo và cấu hình vào các **Service Provider** và tận dụng **Constructor Injection** tự động, nó giúp cho mã nguồn của bạn trở nên:

- **Sạch sẽ và dễ đọc:** Controller và các lớp nghiệp vụ khác chỉ tập trung vào công việc của chúng.
- **Linh hoạt:** Dễ dàng thay đổi cách một service được tạo ra mà không cần sửa code ở nhiều nơi.
- **Dễ bảo trì và kiểm thử (test).**

Đây là một khái niệm nền tảng giúp xây dựng các ứng dụng lớn, phức tạp và có khả năng bảo trì cao trong BaultPHP.
