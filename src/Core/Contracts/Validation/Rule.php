<?php

namespace Core\Contracts\Validation;

/**
 * Interface Rule
 *
 * Defines the contract for a custom validation rule object.
 */
interface Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute The name of the attribute being validated.
     * @param  mixed  $value The value of the attribute.
     * @return bool
     */
    public function passes(string $attribute, mixed $value): bool;

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string;
}