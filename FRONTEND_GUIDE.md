# Hướng Dẫn Tích Hợp Frontend với BaultPHP

Chào mừng các lập trình viên frontend! Tài liệu này sẽ hướng dẫn bạn cách kết nối và tương tác với backend được xây dựng bằng BaultPHP.

BaultPHP là một backend mạnh mẽ, cung cấp các API RESTful và kết nối WebSocket để xây dựng các ứng dụng web động và hiện đại.

## Mục Lục

1.  [Tổng Quan về API](#1-tổng-quan-về-api)
2.  [Xác Thực (Authentication)](#2-xác-thực-authentication)
3.  [Ví Dụ Tương Tác API (JavaScript Fetch)](#3-ví-dụ-tương-tác-api-javascript-fetch)
    - Lấy Danh Sách Bài Viết
    - Tạo Bài Viết Mới
    - Cập Nhật Bài Viết
    - Xóa Bài Viết
4.  [Xử Lý CORS (Cross-Origin Resource Sharing)](#4-xử-lý-cors-cross-origin-resource-sharing)
5.  [Tích Hợp Real-time với WebSocket](#5-tích-hợp-real-time-với-websocket)

---

## 1. Tổng Quan về API

Backend BaultPHP cung cấp các endpoint API theo chuẩn REST. Một ví dụ điển hình là các endpoint quản lý bài viết đã được định nghĩa trong `CRUD_TUTORIAL.md`:

- **`GET /api/posts`**: Lấy danh sách tất cả bài viết.
- **`POST /api/posts`**: Tạo một bài viết mới.
- **`GET /api/posts/{id}`**: Lấy thông tin chi tiết một bài viết.
- **`PUT /api/posts/{id}`**: Cập nhật một bài viết.
- **`DELETE /api/posts/{id}`**: Xóa một bài viết.

Tất cả dữ liệu được trao đổi qua định dạng **JSON**.

---

## 2. Xác Thực (Authentication)

Các endpoint API được bảo vệ sử dụng **JSON Web Tokens (JWT)**. Luồng xác thực diễn ra như sau:

1.  **Đăng nhập**: Frontend gửi `email` và `password` của người dùng đến một endpoint đăng nhập, ví dụ `POST /api/login`.
2.  **Nhận Token**: Nếu thông tin đăng nhập chính xác, backend sẽ trả về một JWT.
    ```json
    {
      "access_token": "ey...",
      "token_type": "Bearer",
      "expires_in": 3600
    }
    ```
3.  **Lưu Token**: Frontend cần lưu `access_token` này một cách an toàn, thường là trong `localStorage` hoặc `sessionStorage`.
4.  **Gửi Token với mỗi Request**: Với mỗi request tiếp theo đến các endpoint được bảo vệ, frontend phải đính kèm token này vào header `Authorization`.

    **Ví dụ Header:**

    ```
    Authorization: Bearer ey...
    ```

Nếu token không hợp lệ hoặc thiếu, backend sẽ trả về lỗi `401 Unauthorized`.

---

## 3. Ví Dụ Tương Tác API (JavaScript Fetch)

Dưới đây là các ví dụ sử dụng `fetch` API của JavaScript để thực hiện các thao tác CRUD với module `Post`.

Giả sử backend đang chạy tại `http://localhost:8080`.

### Lấy Danh Sách Bài Viết

```javascript
async function getPosts() {
  const response = await fetch("http://localhost:8080/api/posts");
  if (!response.ok) {
    throw new Error("Failed to fetch posts");
  }
  const posts = await response.json();
  console.log(posts);
  return posts;
}
```

### Tạo Bài Viết Mới

```javascript
async function createPost(title, content, token) {
  const response = await fetch("http://localhost:8080/api/posts", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`, // Gửi token xác thực
    },
    body: JSON.stringify({ title, content }),
  });

  if (response.status !== 201) {
    // 201 Created
    throw new Error("Failed to create post");
  }
  const newPost = await response.json();
  console.log("Created post:", newPost);
  return newPost;
}
```

### Cập Nhật Bài Viết

```javascript
async function updatePost(postId, title, content, token) {
  const response = await fetch(`http://localhost:8080/api/posts/${postId}`, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ title, content }),
  });

  if (!response.ok) {
    throw new Error("Failed to update post");
  }
  const updatedPost = await response.json();
  console.log("Updated post:", updatedPost);
  return updatedPost;
}
```

### Xóa Bài Viết

```javascript
async function deletePost(postId, token) {
  const response = await fetch(`http://localhost:8080/api/posts/${postId}`, {
    method: "DELETE",
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (response.status !== 204) {
    // 204 No Content
    throw new Error("Failed to delete post");
  }
  console.log("Post deleted successfully");
}
```

---

## 4. Xử Lý CORS (Cross-Origin Resource Sharing)

Khi phát triển, frontend của bạn (ví dụ: `http://localhost:5173`) và backend (`http://localhost:8080`) thường chạy trên hai "origin" khác nhau (do khác cổng). Trình duyệt sẽ chặn các request từ frontend đến backend vì lý do bảo mật, gây ra lỗi **CORS**.

Để khắc phục, backend BaultPHP cần được cấu hình để cho phép các request từ origin của frontend. Điều này thường được thực hiện bằng cách thêm một **Middleware** trong BaultPHP để tự động thêm các HTTP header cần thiết vào mỗi response.

**Các header cần thiết:**

- `Access-Control-Allow-Origin`: Chỉ định origin được phép (ví dụ: `http://localhost:5173` hoặc `*` cho tất cả).
- `Access-Control-Allow-Methods`: Các phương thức HTTP được phép (ví dụ: `GET, POST, PUT, DELETE, OPTIONS`).
- `Access-Control-Allow-Headers`: Các header được phép trong request (ví dụ: `Content-Type, Authorization`).

Hãy liên hệ với đội ngũ backend để đảm bảo middleware này đã được kích hoạt.

---

## 5. Tích Hợp Real-time với WebSocket

BaultPHP sử dụng **Centrifuge** để cung cấp các tính năng real-time qua WebSocket. Điều này rất hữu ích cho các thông báo, chat, hoặc cập nhật dữ liệu trực tiếp.

**Thư viện phía Frontend:**

Bạn có thể sử dụng thư viện `centrifuge-js` để kết nối.

```bash
npm install centrifuge
```

**Ví dụ kết nối và lắng nghe sự kiện:**

```javascript
import { Centrifuge } from "centrifuge";

// Lấy URL và token kết nối từ một endpoint API riêng của backend
const connectionUrl = "ws://localhost:8000/connection/websocket"; // URL của Centrifugo
const connectionToken = "..."; // Token này phải do backend tạo ra cho từng user

const centrifuge = new Centrifuge(connectionUrl, {
  token: connectionToken,
});

// Lắng nghe sự kiện kết nối thành công
centrifuge.on("connected", function (ctx) {
  console.log("Connected to WebSocket server", ctx);
});

// Lắng nghe một channel cụ thể, ví dụ channel 'notifications'
const sub = centrifuge.newSubscription("notifications");

// Lắng nghe các message được publish đến channel này
sub.on("publication", function (ctx) {
  console.log("Received new notification:", ctx.data);
  // Ví dụ: Hiển thị thông báo cho người dùng
});

sub.subscribe();

centrifuge.connect();
```

Tài liệu này cung cấp các thông tin cơ bản để bạn bắt đầu. Chúc bạn xây dựng được một giao diện người dùng tuyệt vời với BaultPHP!
