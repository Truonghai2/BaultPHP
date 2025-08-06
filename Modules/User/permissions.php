<?php

/**
 * Định nghĩa các quyền (capabilities) cho module User.
 * Cấu trúc này lấy cảm hứng từ file access.php của Moodle,
 * giúp định nghĩa quyền một cách có hệ thống và dễ quản lý.
 *
 * 'captype' có thể là 'read' (đọc) hoặc 'write' (ghi/thay đổi dữ liệu).
 * Điều này hữu ích cho việc kiểm tra và báo cáo sau này.
 */

return [
    'users:view' => [
        'description' => 'Xem hồ sơ và danh sách người dùng.',
        'captype' => 'read',
    ],

    'users:create' => [
        'description' => 'Tạo người dùng mới.',
        'captype' => 'write',
    ],

    'users:edit' => [
        'description' => 'Chỉnh sửa thông tin của bất kỳ người dùng nào.',
        'captype' => 'write',
    ],

    'users:delete' => [
        'description' => 'Xóa người dùng khỏi hệ thống.',
        'captype' => 'write',
    ],

    'roles:assign' => [
        'description' => 'Gán vai trò cho người dùng trong các ngữ cảnh cụ thể.',
        'captype' => 'write',
    ],

    'roles:manage' => [
        'description' => 'Tạo, sửa, xóa và quản lý các vai trò.',
        'captype' => 'write',
    ],
];
