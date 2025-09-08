<?php

namespace Core\Exceptions;

use Core\Validation\Validator;

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
        parent::__construct('The given data was invalid.');
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
        $validator = new Validator([], []);
        $validator->setErrors($messages);

        return new static($validator);
    }
}
