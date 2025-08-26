<?php

namespace App\Http\Requests;

use Http\FormRequest;

class CreateAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * In this specific case, anyone is authorized if no admin exists yet.
     * The middleware `EnsureAdminUserExists` already handles the redirection logic.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
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
