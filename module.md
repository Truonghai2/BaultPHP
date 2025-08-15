Modules/
└── Post/
├── Application/
│ ├── Commands/ # (Use Cases) Các lệnh ghi dữ liệu (CUD)
│ │ └── CreatePostCommand.php
│ ├── Handlers/ # Xử lý các Command
│ │ └── CreatePostHandler.php
│ ├── Queries/ # (Use Cases) Các lệnh đọc dữ liệu (R)
│ ├── Policies/ # Các lớp Policy phân quyền
│ │ └── PostPolicy.php
│ └── Listeners/ # Xử lý các Event
│
├── Domain/
│ ├── Entities/ # Các đối tượng nghiệp vụ cốt lõi (có định danh)
│ │ └── Post.php # (Có thể là Model nếu dùng Active Record)
│ ├── Events/ # Các sự kiện nghiệp vụ
│ │ └── PostWasCreated.php
│ ├── Repositories/ # Các Interface cho việc truy cập dữ liệu
│ │ └── PostRepositoryInterface.php
│ └── Services/ # Logic nghiệp vụ phức tạp không thuộc về Entity nào
│
├── Infrastructure/
│ ├── Migrations/ # Database migrations
│ │ └── 2025_07_16_120000_create_posts_table.php
│ ├── Models/ # Các ORM Model (triển khai của Entity)
│ │ └── Post.php # (Nếu tách biệt với Entity)
│ └── Repositories/ # Triển khai cụ thể của Repository Interfaces
│ └── EloquentPostRepository.php
│
├── Http/
│ ├── Controllers/ # Chỉ điều phối request, gọi Use Case
│ │ └── PostController.php
│ └── Requests/ # Các FormRequest để validate
│ └── CreatePostRequest.php
│
└── Providers/
└── PostServiceProvider.php # Đăng ký mọi thứ của module này
