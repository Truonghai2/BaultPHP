<?php

namespace Core\Validation;

use Core\Application;
use PDO;

/**
 * A simple, custom validator class.
 */
class Validator
{
    protected Application $app;
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected array $errors = [];
    protected ?PDO $connection = null;

    public function __construct(Application $app, array $data, array $rules, array $messages = [])
    {
        $this->app = $app;
        $this->data = $data;
        $this->rules = $this->parseRules($rules);
        $this->messages = $messages;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function passes(): bool
    {
        $this->errors = []; // Reset errors before each run

        foreach ($this->rules as $attribute => $rules) {
            $value = $this->data[$attribute] ?? null;

            foreach ($rules as $rule) {
                [$ruleName, $parameters] = $this->parseRule($rule);
                $method = 'validate' . ucfirst($ruleName);

                if (!method_exists($this, $method)) {
                    // For simplicity, we skip unknown rules. An exception could be thrown here.
                    continue;
                }

                if (!$this->$method($attribute, $value, $parameters)) {
                    $this->addError($attribute, $ruleName, $parameters);
                    // Stop validating this attribute after the first failure for efficiency.
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function addError(string $attribute, string $rule, array $parameters): void
    {
        $message = $this->getMessage($attribute, $rule);

        // Replace placeholders
        $message = str_replace(':attribute', $attribute, $message);
        if (!empty($parameters)) {
            // This is a simple replacement for rules like min:value, max:value
            $message = str_replace(':min', $parameters[0], $message);
            $message = str_replace(':max', $parameters[0], $message);
        }

        $this->errors[$attribute][] = $message;
    }

    protected function getMessage(string $attribute, string $rule): string
    {
        if (isset($this->messages["{$attribute}.{$rule}"])) {
            return $this->messages["{$attribute}.{$rule}"];
        }

        if (isset($this->messages[$rule])) {
            return $this->messages[$rule];
        }

        return match ($rule) {
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'string' => 'The :attribute must be a string.',
            'min' => 'The :attribute must be at least :min characters/items/value.',
            'max' => 'The :attribute must not be greater than :max characters/items/value.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            default => "The :attribute field is invalid.",
        };
    }

    protected function parseRules(array $rules): array
    {
        $parsed = [];
        foreach ($rules as $attribute => $ruleString) {
            $parsed[$attribute] = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
        }
        return $parsed;
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

    // --- Validation Methods ---

    /**
     * Get the database connection.
     */
    protected function getConnection(): PDO
    {
        if ($this->connection) {
            return $this->connection;
        }
        // Assumes PDO is bound in the container. A DatabaseServiceProvider should handle this.
        return $this->connection = $this->app->make(PDO::class);
    }

    protected function validateRequired(string $attribute, $value, array $parameters): bool
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif (is_array($value) && empty($value)) {
            return false;
        }
        return true;
    }

    protected function validateString(string $attribute, $value, array $parameters): bool
    {
        return is_null($value) || is_string($value);
    }

    protected function validateEmail(string $attribute, $value, array $parameters): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $attribute, $value, array $parameters): bool
    {
        if (empty($parameters[0])) return false;
        $min = (int) $parameters[0];

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        } elseif (is_numeric($value)) {
            return $value >= $min;
        } elseif (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    protected function validateMax(string $attribute, $value, array $parameters): bool
    {
        if (empty($parameters[0])) return false;
        $max = (int) $parameters[0];

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        } elseif (is_numeric($value)) {
            return $value <= $max;
        } elseif (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    /**
     * Validate that an attribute value exists in a database table.
     * Rule: exists:table,column
     */
    protected function validateExists(string $attribute, $value, array $parameters): bool
    {
        if (count($parameters) < 1) {
            // Or throw an exception for invalid rule definition
            return false;
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $attribute;

        $stmt = $this->getConnection()->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?"
        );

        $stmt->execute([$value]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Validate that an attribute value is unique in a database table.
     * Rule: unique:table,column,except,idColumn
     */
    protected function validateUnique(string $attribute, $value, array $parameters): bool
    {
        if (count($parameters) < 1) {
            return false;
        }

        $table = $parameters[0];
        $column = $parameters[1] ?? $attribute;
        $except = $parameters[2] ?? null;
        $idColumn = $parameters[3] ?? 'id';

        $query = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $bindings = [$value];

        if ($except !== null) {
            $query .= " AND `{$idColumn}` != ?";
            $bindings[] = $except;
        }

        $stmt = $this->getConnection()->prepare($query);
        $stmt->execute($bindings);

        return $stmt->fetchColumn() == 0;
    }
}