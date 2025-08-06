<?php

namespace Core\Validation;

use Core\Contracts\Validation\Rule as RuleContract;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected array $errors = [];
    protected array $validatedData = [];
    protected array $extensions = [];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $this->normalizeRules($rules);
        $this->messages = $messages;
    }

    /**
     * Add custom validation extensions to the validator.
     */
    public function addExtensions(array $extensions): void
    {
        $this->extensions = array_merge($this->extensions, $extensions);
    }

    /**
     * Run the validator's rules against its data.
     */
    protected function validate(): void
    {
        foreach ($this->rules as $attribute => $rules) {
            $value = $this->data[$attribute] ?? null;

            foreach ($rules as $rule) {
                $this->validateAttribute($attribute, $value, $rule);
            }
        }

        if (empty($this->errors)) {
            $this->validatedData = array_intersect_key($this->data, $this->rules);
        }
    }

    protected function validateAttribute(string $attribute, mixed $value, mixed $rule): void
    {
        if ($rule instanceof RuleContract) {
            if (!$rule->passes($attribute, $value)) {
                $this->addError($attribute, get_class($rule), $rule->message());
            }
            return;
        }

        [$ruleName, $parameters] = $this->parseRule($rule);

        if (isset($this->extensions[$ruleName])) {
            $this->validateExtension($attribute, $value, $ruleName, $parameters);
            return;
        }

        // Placeholder for built-in rules.
        // In a real implementation, you would have methods like validateRequired, validateEmail, etc.
        $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));
        if (method_exists($this, $method)) {
            $this->{$method}($attribute, $value, $parameters);
        }
    }

    protected function validateExtension(string $attribute, mixed $value, string $ruleName, array $parameters): void
    {
        $extensionData = $this->extensions[$ruleName];
        $callback = $extensionData['extension'];
        $message = $extensionData['message'] ?? 'The :attribute field is invalid.';

        if (!$callback($attribute, $value, $parameters, $this)) {
            $this->addError($attribute, $ruleName, $message);
        }
    }

    protected function parseRule(string $rule): array
    {
        $parameters = [];
        if (str_contains($rule, ':')) {
            [$rule, $parameterString] = explode(':', $rule, 2);
            $parameters = explode(',', $parameterString);
        }
        return [$rule, $parameters];
    }

    protected function normalizeRules(array $rules): array
    {
        return array_map(function ($rules) {
            return is_string($rules) ? explode('|', $rules) : (array) $rules;
        }, $rules);
    }

    protected function addError(string $attribute, string $rule, string $message): void
    {
        $this->errors[$attribute][] = str_replace(':attribute', $attribute, $message);
    }

    /**
     * Determine if the data fails the validation rules.
     */
    public function fails(): bool
    {
        // Run validation if it hasn't been run yet.
        if (empty($this->errors) && empty($this->validatedData)) {
            $this->validate();
        }
        return !empty($this->errors);
    }

    /**
     * Get the validation error messages.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the attributes and values that were validated.
     *
     * @return array
     * @throws \Core\Exceptions\ValidationException
     */
    public function validated(): array
    {
        if ($this->fails()) {
            // This behavior is consistent with FormRequest, which expects an exception on failure.
            throw new \Core\Exceptions\ValidationException($this);
        }
        return $this->validatedData;
    }
}
