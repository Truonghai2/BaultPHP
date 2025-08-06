<?php

namespace App\Exceptions;

/**
 * Ném ra khi CSRF token không khớp.
 * Exception này thường được ExceptionHandler bắt lại để trả về response 419.
 */
class TokenMismatchException extends \Exception
{
    // Bạn có thể thêm các logic tùy chỉnh ở đây nếu cần trong tương lai.
}
