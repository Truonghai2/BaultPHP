<?php

namespace App\Rules;

use Core\Contracts\Validation\Rule;

/**
 * Class Rule này đóng gói logic kiểm tra từ ngữ không phù hợp.
 */
class NoProfanityRule implements Rule
{
    /**
     * Xác định xem quy tắc validation có được thông qua hay không.
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Lấy danh sách từ file config để dễ quản lý
        $forbiddenWords = config('profanity.words', []);

        str_ireplace($forbiddenWords, '***', $value, $count);
        return $count === 0;
    }

    /**
     * Lấy thông báo lỗi validation.
     */
    public function message(): string
    {
        // Sử dụng helper __() để lấy message từ file lang
        return __('validation.no_profanity');
    }
}
