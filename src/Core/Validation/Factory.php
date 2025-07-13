<?php

namespace Core\Validation;

use Core\Application;

/**
 * Creates Validator instances.
 * This class acts as the main entry point for the validation system.
 */
class Factory
{
    public function __construct(protected Application $app)
    {
        // The factory might need the app container later, e.g., for custom rule objects.
    }

    public function make(array $data, array $rules, array $messages = []): Validator
    {
        // In a more advanced version, we could load default messages from a config file here.
        return new Validator($this->app, $data, $rules, $messages);
    }
}