<?php

namespace Core\Validation;

class ValidationException extends \Exception
{
    /**
     * The validator instance.
     *
     * @var \Core\Validation\Validator
     */
    public Validator $validator;

    /**
     * Create a new validation exception instance.
     *
     * @param  \Core\Validation\Validator  $validator
     */
    public function __construct(Validator $validator)
    {
        // Sử dụng mã lỗi 422 (Unprocessable Entity) là chuẩn cho lỗi validation.
        parent::__construct('The given data was invalid.', 422);
        $this->validator = $validator;
    }

    /**
     * Create a new validation exception with a given array of messages.
     *
     * @param  array  $messages
     * @return static
     */
    public static function withMessages(array $messages): static
    {
        // Tạo một validator "giả" để bọc các message lỗi.
        // Điều này giữ cho cấu trúc của exception nhất quán.
        $validator = new Validator([], []);
        $validator->setErrors($messages);

        return new static($validator);
    }

    /**
     * Lấy các thông báo lỗi từ validator.
     */
    public function errors(): array
    {
        return $this->validator->errors();
    }
}
