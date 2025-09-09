<?php

namespace Modules\User\Http\Requests;

use Core\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Đây là nơi để kiểm tra xem người dùng đã xác thực có quyền gán vai trò hay không.
        // Ví dụ: return $this->user()->can('user.assign_role');
        // Hiện tại, chúng ta sẽ cho phép để phục vụ demo.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'role_id' => 'required|integer|exists:roles,id',
            'context_level' => 'required|string|in:system,course,module',
            'instance_id' => 'required|integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'context_level.in' => 'The context level must be one of the following types: system, course, module.',
        ];
    }
}
