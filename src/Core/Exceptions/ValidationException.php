<?php

namespace Core\Exceptions;

use Core\Validation\Validator;

class ValidationException extends \Exception
{
    /**
     * The validator instance.
     */
    public Validator $validator;

    public function __construct(Validator $validator, string $message = 'The given data was invalid.', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->validator = $validator;
    }

    public function errors(): array
    {
        return $this->validator->errors();
    }
}
