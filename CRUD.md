# Hướng Dẫn: Xây Dựng CRUD Hoàn Chỉnh Với BaultPHP

Tài liệu này sẽ hướng dẫn bạn qua từng bước để tạo một module quản lý bài viết (`Post`) hoàn chỉnh, bao gồm các chức năng Tạo, Đọc, Cập nhật và Xóa (CRUD) thông qua API.

## Mục tiêu

Chúng ta sẽ xây dựng các endpoint API sau:

- `GET /api/posts`: Lấy danh sách tất cả bài viết.
- `POST /api/posts`: Tạo một bài viết mới.
- `GET /api/posts/{id}`: Lấy thông tin chi tiết một bài viết.
- `PUT /api/posts/{id}`: Cập nhật một bài viết.
- `DELETE /api/posts/{id}`: Xóa một bài viết.

## Bước 1: Tạo Module `Post`

Đầu tiên, hãy sử dụng công cụ CLI để tạo một module mới tên là `Post`. Mở terminal và chạy lệnh:

```bash
php cli ddd:make-module Post
```

Lệnh này sẽ tạo ra toàn bộ cấu trúc thư mục cần thiết bên trong `Modules/Post`, sẵn sàng để chúng ta phát triển.

## Bước 2: Tạo Database Migration

Tiếp theo, chúng ta cần định nghĩa cấu trúc cho bảng `posts` trong cơ sở dữ liệu.

1.  Tạo một file migration mới. Tên file nên theo định dạng `YYYY_MM_DD_His_create_posts_table.php`.
    **Tạo file:** `Modules/Post/Infrastructure/Migrations/2025_07_16_120000_create_posts_table.php`

2.  Thêm nội dung sau vào file migration vừa tạo:

    ```php
    <?php

    use Core\Database\Migration;
    use Illuminate\Database\Schema\Blueprint;

    return new class extends Migration
    {
        public function up(): void
        {
            $this->schema->create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->timestamps(); // Tự động tạo 2 cột created_at và updated_at
            });
        }

        public function down(): void
        {
            $this->schema->dropIfExists('posts');
        }
    };
    ```

## Bước 3: Tạo Model

Bây giờ, hãy tạo ORM Model để tương tác với bảng `posts`.

1.  **Tạo file:** `Modules/Post/Infrastructure/Models/Post.php`
2.  Thêm nội dung:

    ```php
    <?php

    namespace Modules\Post\Infrastructure\Models;

    use Core\ORM\Model;

    class Post extends Model
    {
        // Tên bảng trong CSDL
        protected static string $table = 'posts';

        // Các trường được phép gán hàng loạt (mass-assignment)
        protected $fillable = [
            'title',
            'content',
        ];
    }
    ```

## Bước 4: Chạy Migration

Sau khi đã có file migration và model, hãy chạy lệnh sau để tạo bảng `posts` trong CSDL:

```bash
php cli ddd:migrate
```

Bạn sẽ thấy thông báo cho biết migration đã được chạy thành công.

## Bước 5: Tạo Controller và Định nghĩa Routes

Chúng ta sẽ tạo một `PostController` và sử dụng **Attribute-based Routing** để định nghĩa các endpoint.

1.  **Tạo file:** `Modules/Post/Http/Controllers/PostController.php`
2.  Thêm nội dung khởi tạo cho controller. Chúng ta sẽ điền logic cho các phương thức ở bước tiếp theo.

    ```php
    <?php

    namespace Modules\Post\Http\Controllers;

    use Core\Routing\Attributes\Route;
    use Http\Request;
    use Core\Events\EventDispatcherInterface;
    use Http\Response;
    use Modules\Post\Infrastructure\Models\Post;

    class PostController
    {
        #[Route('/api/posts', method: 'GET')]
        public function index(): Response
        {
            // Logic để lấy danh sách bài viết
        }

        #[Route('/api/posts', method: 'POST')]
        public function store(Request $request, EventDispatcherInterface $dispatcher): Response
        {
            // Logic để tạo bài viết mới
        }

        #[Route('/api/posts/{id}', method: 'GET')]
        public function show(int $id): Response
        {
            // Logic để xem chi tiết một bài viết
        }

        #[Route('/api/posts/{id}', method: 'PUT')]
        public function update(Request $request, int $id): Response
        {
            // Logic để cập nhật bài viết
        }

        #[Route('/api/posts/{id}', method: 'DELETE')]
        public function destroy(int $id): Response
        {
            // Logic để xóa bài viết
        }
    }
    ```

**Lưu ý:** Framework sẽ tự động quét và đăng ký các route được định nghĩa bằng Attribute. Để tăng tốc trong môi trường production, bạn có thể chạy `php cli route:cache`.

## Bước 6: Hoàn Thiện Logic CRUD trong Controller

Bây giờ, chúng ta sẽ điền logic cho từng phương thức trong `PostController`.

### a. Lấy danh sách (Read - Index)

Cập nhật phương thức `index()`:

```php
    #[Route('/api/posts', method: 'GET')]
    public function index(): Response
    {
        $posts = Post::all();
        return response()->json($posts);
    }
```

### b. Tạo mới (Create - Store)

Cập nhật phương thức `store()`:

```php
    #[Route('/api/posts', method: 'POST')]
    public function store(Request $request, EventDispatcherInterface $dispatcher): Response
    {
        // Đơn giản, chúng ta sẽ validate trực tiếp ở đây.
        // Trong thực tế, bạn nên tạo một FormRequest riêng.
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post = Post::create($validated);

        // Bắn ra sự kiện để các hệ thống khác có thể xử lý
        $dispatcher->dispatch(new \Modules\Post\Domain\Events\PostWasCreated($post));

        return response()->json($post, 201); // 201 Created
    }
```

### c. Xem chi tiết (Read - Show)

Cập nhật phương thức `show()`:

```php
    #[Route('/api/posts/{id}', method: 'GET')]
    public function show(int $id): Response
    {
        $post = Post::findOrFail($id);
        return response()->json($post);
    }
```

### d. Cập nhật (Update)

Cập nhật phương thức `update()`:

```php
    #[Route('/api/posts/{id}', method: 'PUT')]
    public function update(Request $request, int $id): Response
    {
        $post = Post::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post->update($validated);

        return response()->json($post);
    }
```

### e. Xóa (Delete - Destroy)

Cập nhật phương thức `destroy()`:

```php
    #[Route('/api/posts/{id}', method: 'DELETE')]
    public function destroy(int $id): Response
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(null, 204); // 204 No Content
    }
```

## Bước 7: Kiểm tra API

Bây giờ module CRUD của bạn đã hoàn tất! Hãy khởi động server:

```bash
php cli serve
```

Bạn có thể sử dụng một công cụ như **Postman**, **Insomnia**, hoặc `curl` để kiểm tra các endpoint:

**Tạo một bài viết mới:**

```bash
curl -X POST http://localhost:8080/api/posts \
     -H "Content-Type: application/json" \
     -d '{"title": "My First Post", "content": "This is the content of my first post."}'
```

**Lấy danh sách bài viết:**

```bash
curl http://localhost:8080/api/posts
```

**Cập nhật bài viết có ID là 1:**

```bash
curl -X PUT http://localhost:8080/api/posts/1 \
     -H "Content-Type: application/json" \
     -d '{"title": "My Updated Post", "content": "Content has been updated."}'
```

**Xóa bài viết có ID là 1:**

```bash
curl -X DELETE http://localhost:8080/api/posts/1
```

## Tổng kết

Chúc mừng! Bạn đã xây dựng thành công một module CRUD hoàn chỉnh trong BaultPHP. Từ đây, bạn có thể áp dụng các khái niệm nâng cao hơn như:

- Tạo các class `FormRequest` riêng để xử lý validation phức tạp.
- Tách logic nghiệp vụ ra các lớp `Use Case` trong thư mục `Application` của module.
- Sử dụng `Repository Pattern` để trừu tượng hóa việc truy cập dữ liệu.
- Thêm cơ chế xác thực và phân quyền cho các endpoint.
