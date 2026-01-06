<?php

namespace Modules\Admin\Http\Requests;

use Core\Http\FormRequest;

class InstallModulesRequests extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * In this case, anyone can attempt to log in, so we return true.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
        ];
    }
}
