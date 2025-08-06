<?php

namespace Core\Validation;

/**
 * Exception tùy chỉnh cho các lỗi validation trong framework.
 * Việc này giúp tách biệt code của bạn khỏi exception cụ thể của thư viện bên thứ ba.
 */
class ValidationException extends \Exception
{
    /**
     * Mảng chứa các lỗi validation.
     *
     * @var array
     */
    public array $errors;

    /**
     * Tạo một instance exception mới.
     */
    public function __construct(array $errors, string $message = 'The given data was invalid.', int $code = 422, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Lấy danh sách các lỗi validation.
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
