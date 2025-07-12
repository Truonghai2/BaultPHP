<?php

namespace Modules\User\Http\Requests;

use Http\FormRequest;
use Core\Support\Facades\Auth;

class StoreUserRequest extends FormRequest
{
    /**
     * Xác định xem người dùng có được phép thực hiện request này hay không.
     */
    public function authorize(): bool
    {
        // Ví dụ: Chỉ người dùng đã đăng nhập mới có thể tạo người dùng mới.
        // Trong ứng dụng thực tế, bạn có thể kiểm tra quyền hạn (permission).
        return Auth::check();
    }

    /**
     * Lấy các quy tắc xác thực áp dụng cho request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Giả sử bạn đã có `DatabaseServiceProvider` để rule `unique` hoạt động
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}