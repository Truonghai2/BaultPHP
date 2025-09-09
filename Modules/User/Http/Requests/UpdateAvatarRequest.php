<?php

namespace Modules\User\Http\Requests;

use Core\Http\FormRequest;
use Core\Support\Facades\Auth;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * Xác định xem người dùng có quyền cập nhật avatar hay không.
     * Trong trường hợp này, chỉ cần người dùng đã đăng nhập.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Định nghĩa các quy tắc validation cho việc upload avatar.
     */
    public function rules(): array
    {
        return [
            'avatar' => 'required|file|mimes:jpg,jpeg,png,gif|max:2048', // max 2MB
        ];
    }

    /**
     * Tùy chỉnh thông báo lỗi.
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Vui lòng chọn một file ảnh để tải lên.',
            'avatar.mimes' => 'Chỉ chấp nhận các định dạng ảnh: jpg, jpeg, png, gif.',
            'avatar.max' => 'Kích thước ảnh không được vượt quá 2MB.',
        ];
    }
}
