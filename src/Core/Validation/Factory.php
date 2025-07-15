<?php

namespace Core\Validation;

use Core\Application;

class Factory
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The registered custom validator extensions.
     *
     * @var array
     */
    protected array $extensions = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register a custom validation rule.
     */
    public function extend(string $rule, \Closure|string $extension, ?string $message = null): void
    {
        $this->extensions[$rule] = compact('extension', 'message');
    }

    /**
     * Create a new Validator instance.
     */
    public function make(array $data, array $rules, array $messages = []): Validator
    {
        $validator = new Validator($data, $rules, $messages);
        $validator->addExtensions($this->extensions);
        return $validator;
    }
}