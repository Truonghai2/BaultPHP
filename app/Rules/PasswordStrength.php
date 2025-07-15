<?php

namespace App\Rules;

use Core\Contracts\Validation\Rule;

class PasswordStrength implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * The rule requires the password to be at least 8 characters long,
     * contain at least one uppercase letter, one lowercase letter, and one number.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return strlen($value) >= 8
            && preg_match('/[A-Z]/', $value)
            && preg_match('/[a-z]/', $value)
            && preg_match('/[0-9]/', $value);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Mật khẩu phải dài ít nhất 8 ký tự, chứa chữ hoa, chữ thường và số.';
    }
}