<?php

declare(strict_types=1);

namespace Modules\User\Http\Requests;

use Http\FormRequest;

/**
 * Handles the validation for updating a user.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // For a real application, you'd check if the authenticated user
        // has permission to update the target user.
        // Example: return $this->user()->can('update', $this->route('id'));
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $userId,
            'password' => 'sometimes|nullable|string|min:8',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'The email address is already in use by another user.',
            'password.min' => 'The password must be at least 8 characters long.',
        ];
    }
}
