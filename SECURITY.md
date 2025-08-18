# Cơ Chế Bảo Mật trong BaultPHP

BaultPHP là một framework được xây dựng từ đầu với các nguyên tắc bảo mật hiện đại làm cốt lõi. Framework tích hợp nhiều cơ chế mạnh mẽ để bảo vệ ứng dụng của bạn khỏi các lỗ hổng web phổ biến, đảm bảo tính toàn vẹn dữ liệu và kiểm soát truy cập chặt chẽ.

## 1. Xác thực (Authentication)

BaultPHP cung cấp một hệ thống xác thực linh hoạt, hỗ trợ cả hai phương pháp phổ biến:

- **`JwtGuard` (API không trạng thái - Stateless):** Dành cho các API, BaultPHP sử dụng JSON Web Tokens (JWT). Client sau khi đăng nhập sẽ nhận được một token và phải gửi kèm token này trong header `Authorization: Bearer <token>` với mỗi request. Cơ chế này lý tưởng cho các ứng dụng Single Page Application (SPA) hoặc ứng dụng di động.
- **`SessionGuard` (Web có trạng thái - Stateful):** Dành cho các ứng dụng web truyền thống, BaultPHP sử dụng session và cookie để duy trì trạng thái đăng nhập của người dùng.

Cả hai cơ chế này đều được cấu hình trong `config/auth.php` và có thể dễ dàng áp dụng cho các route thông qua middleware.

## 2. Phân quyền chi tiết (Fine-Grained Authorization)

Trái tim của hệ thống phân quyền là `AccessControlService`, một dịch vụ mạnh mẽ cho phép kiểm soát truy cập ở mức độ chi tiết.

- **Policies:** Cho phép bạn định nghĩa logic phân quyền cho các model cụ thể. Ví dụ, một `PostPolicy` có thể định nghĩa ai có quyền `view`, `create`, `update`, hoặc `delete` một bài viết.
- **Role-Based Access Control (RBAC) theo Ngữ cảnh:** Quyền hạn không chỉ được gán cho người dùng mà còn phụ thuộc vào vai trò (Role) của họ trong một ngữ cảnh (Context) cụ thể. Hệ thống hỗ trợ phân cấp ngữ cảnh, cho phép quyền được kế thừa từ cha xuống con (ví dụ: quyền ở cấp "Khóa học" sẽ được áp dụng cho các "Bài học" bên trong).
- **Tối ưu hóa hiệu năng:** Dịch vụ này sử dụng nhiều lớp cache (cache trong một request và cache bền vững như Redis) để giảm thiểu truy vấn CSDL khi kiểm tra quyền, tránh vấn đề N+1.
- **Helper `authorize()`:** Các controller có thể sử dụng phương thức `authorize('permission-name', $model)` để dễ dàng thực hiện kiểm tra quyền. Nếu không được phép, một exception sẽ được ném ra, ngăn chặn hành động.

```php
// Ví dụ trong một Controller
use Core\Http\Controller;

class PostController extends Controller
{
    public function update(Request $request, int $id): Response
    {
        $post = Post::findOrFail($id);

        // Dễ dàng kiểm tra quyền trước khi thực hiện hành động
        $this->authorize('post:update', $post);

        // ... logic cập nhật
    }
}
```

## 3. Chống Tấn Công SQL Injection

ORM và Query Builder được xây dựng riêng cho BaultPHP sử dụng **parameter binding** (ràng buộc tham số) cho tất cả các truy vấn. Điều này đảm bảo rằng mọi dữ liệu đầu vào từ người dùng luôn được xử lý như là dữ liệu, chứ không phải là một phần của câu lệnh SQL. Cơ chế này vô hiệu hóa hoàn toàn các cuộc tấn công SQL injection.

```php
// An toàn: $id được xử lý như một tham số, không phải code SQL.
$post = Post::findOrFail($id);

// An toàn: Dữ liệu trong $validated được bind vào câu lệnh.
$post->update($validated);
```

## 4. Xác Thực và Phân Quyền (Authentication & Authorization)

## 4. Chống Tấn Công Cross-Site Scripting (XSS)

BaultFrame cung cấp hệ thống xác thực và phân quyền hoàn chỉnh.
Bảo vệ chống lại XSS là trách nhiệm của lớp View (hiển thị). Khi render HTML ở phía server, framework cần đảm bảo rằng mọi dữ liệu xuất ra đều được escape (chuyển đổi các ký tự đặc biệt thành các thực thể HTML).

- Component Rendering: Trong BaultPHP, các component phía server (tương tự Livewire) render HTML trên backend. Logic này phải đảm bảo dữ liệu được làm sạch trước khi gửi về cho client.
- Cảnh báo: Luôn cẩn trọng khi hiển thị dữ liệu mà không qua cơ chế escape. Chỉ làm vậy khi bạn hoàn toàn tin tưởng vào nguồn gốc và sự an toàn của dữ liệu đó.

- Guards & Providers: Cấu hình trong config/auth.php, cho phép nhiều cơ chế xác thực khác nhau (session, token).
- Gates & Policies: Cho phép định nghĩa các quy tắc phân quyền chi tiết để kiểm soát quyền truy cập của người dùng vào các tài nguyên.
- Middleware auth: Dễ dàng bảo vệ các route chỉ cho phép người dùng đã xác thực truy cập. +## 5. Bảo mật Component tương tác (Component Security)

## 5. Mã Hóa và Hashing +BaultPHP có một hệ thống component tương tác mạnh mẽ, và nó được thiết kế với nhiều lớp bảo mật:

- Chống giả mạo dữ liệu (Snapshot Tampering): Mỗi "snapshot" (trạng thái) của component gửi từ client lên server đều đi kèm một checksum. Server sẽ sử dụng ChecksumService để xác thực checksum này bằng hash_hmac với một khóa bí mật (APP_KEY). Nếu dữ liệu đã bị sửa đổi ở phía client, checksum sẽ không hợp lệ và request sẽ bị từ chối.
- Kiểm soát phương thức được gọi (Method Authorization): Không phải mọi phương thức public trên một class component đều có thể được gọi từ frontend. Lập trình viên phải đánh dấu tường minh các phương thức an toàn bằng attribute #[CallableMethod]. Bất kỳ lời gọi nào đến một phương thức không có attribute này sẽ bị chặn.
- Kiểm soát khởi tạo Component (Class Whitelisting): Hệ thống chỉ cho phép khởi tạo các class kế thừa từ Core\Frontend\Component, ngăn chặn việc kẻ tấn công yêu cầu server khởi tạo các class tùy ý.

Laravel cung cấp các dịch vụ mã hóa và hashing mạnh mẽ.

## 6. Xác thực dữ liệu đầu vào (Input Validation)

- Hashing: Sử dụng Bcrypt và Argon2 để hash mật khẩu một cách an toàn.
- Encryption: Sử dụng AES-256 và AES-128 để mã hóa và giải mã dữ liệu. Mọi giá trị được mã hóa đều được ký bằng Message Authentication Code (MAC) để chống lại việc thay đổi dữ liệu đã được mã hóa. Yêu cầu APP_KEY trong file .env. +BaultPHP cung cấp một hệ thống validation mạnh mẽ và dễ sử dụng.
- Helper validate(): Lớp Controller cơ sở cung cấp một phương thức validate() tiện lợi. Nó nhận dữ liệu và các quy tắc, và nếu validation thất bại, nó sẽ tự động ném ra một ValidationException.
- Tự động Response: Exception này sẽ được bắt bởi exception handler trung tâm và chuyển thành một HTTP response phù hợp (thường là 422 Unprocessable Entity với danh sách lỗi dưới dạng JSON), giúp phía frontend dễ dàng xử lý.

## 6. Quản lý CORS (Cross-Origin Resource Sharing)

```php
// Ví dụ trong CRUD.md
$validated = $request->validate([

'title' => 'required|string|max:255',
'content' => 'required|string']);
```

File cấu hình config/cors.php cho phép bạn quản lý các chính sách CORS, kiểm soát domain nào có thể truy cập vào API của bạn. +## 7. Chống Tấn Công Cross-Site Request Forgery (CSRF)

## 7. Xác thực dữ liệu đầu vào (Input Validation) +Đối với các ứng dụng web có trạng thái (sử dụng SessionGuard), BaultPHP cần một cơ chế bảo vệ chống lại tấn công CSRF. +_ CSRF Tokens: Mỗi session người dùng sẽ có một token CSRF duy nhất. Token này phải được đính kèm vào tất cả các request POST, PUT, PATCH, DELETE. +_ Middleware: Một middleware sẽ tự động kiểm tra sự tồn tại và hợp lệ của token này, đảm bảo request được gửi từ chính ứng dụng chứ không phải từ một trang web độc hại khác.

Laravel cung cấp một hệ thống validation mạnh mẽ để xác thực dữ liệu đến từ request.

## 8. Quản lý CORS (Cross-Origin Resource Sharing)

- Form Requests: Tạo các class Form Request riêng để đóng gói logic validation phức tạp.
- Hàng trăm quy tắc validation: Cung cấp nhiều quy tắc có sẵn (required, email, min, max, unique,...) và cho phép tạo quy tắc tùy chỉnh.
  +Khi frontend và backend chạy trên hai origin khác nhau (ví dụ localhost:5173 và localhost:8080), trình duyệt sẽ áp dụng chính sách Same-Origin Policy. BaultPHP cần được cấu hình để cho phép các request từ origin của frontend. Điều này được thực hiện bằng một Middleware CORS, có nhiệm vụ thêm các HTTP header cần
