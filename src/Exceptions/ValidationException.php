<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Validation\Validator;

class ValidationException extends Exception
{
    /**
     * The validator instance.
     */
    public Validator $validator;

    public function __construct(Validator $validator, string $message = "The given data was invalid.", int $code = 422)
    {
        parent::__construct($message, $code);
        $this->validator = $validator;
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->validator->errors()->messages();
    }
}