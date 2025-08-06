<?php

namespace Modules\User\Http\Requests;

use Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Ví dụ: chỉ có admin mới được phép tạo người dùng mới.
        // return $this->user?->can('user.create') ?? false;

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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ];
    }
}
