<?php

namespace Core\Support;

/**
 * Result pattern for explicit error handling.
 * 
 * Inspired by Railway Oriented Programming and Rust's Result<T, E>.
 * Provides type-safe error handling without exceptions.
 * 
 * Usage:
 * ```php
 * // Success
 * return Result::ok($data);
 * 
 * // Failure
 * return Result::fail($error);
 * 
 * // Check result
 * if ($result->isSuccess()) {
 *     $data = $result->getValue();
 * } else {
 *     $error = $result->getError();
 * }
 * 
 * // Chaining
 * $result->map(fn($x) => $x * 2)
 *        ->flatMap(fn($x) => someOperation($x))
 *        ->match(
 *            success: fn($v) => "Got: $v",
 *            failure: fn($e) => "Error: $e"
 *        );
 * ```
 * 
 * @template T
 */
class Result
{
    private function __construct(
        private readonly bool $isSuccess,
        private readonly mixed $value = null,
        private readonly mixed $error = null
    ) {}

    /**
     * Create a successful result.
     * 
     * @template U
     * @param U $value
     * @return Result<U>
     */
    public static function ok(mixed $value = null): self
    {
        return new self(true, $value, null);
    }

    /**
     * Create a failed result.
     * 
     * @param mixed $error
     * @return Result<never>
     */
    public static function fail(mixed $error): self
    {
        return new self(false, null, $error);
    }

    /**
     * Check if result is successful.
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * Check if result is failure.
     */
    public function isFailure(): bool
    {
        return !$this->isSuccess;
    }

    /**
     * Get the value (throws if failure).
     * 
     * @return T
     * @throws \RuntimeException
     */
    public function getValue(): mixed
    {
        if (!$this->isSuccess) {
            throw new \RuntimeException('Cannot get value from failed result');
        }

        return $this->value;
    }

    /**
     * Get the error (throws if success).
     * 
     * @throws \RuntimeException
     */
    public function getError(): mixed
    {
        if ($this->isSuccess) {
            throw new \RuntimeException('Cannot get error from successful result');
        }

        return $this->error;
    }

    /**
     * Get value or default if failure.
     * 
     * @param mixed $default
     * @return mixed
     */
    public function getValueOr(mixed $default): mixed
    {
        return $this->isSuccess ? $this->value : $default;
    }

    /**
     * Get value or compute default if failure.
     * 
     * @param callable $fn
     * @return mixed
     */
    public function getValueOrElse(callable $fn): mixed
    {
        return $this->isSuccess ? $this->value : $fn($this->error);
    }

    /**
     * Map the value if successful.
     * 
     * @template U
     * @param callable(T): U $fn
     * @return Result<U>
     */
    public function map(callable $fn): self
    {
        if (!$this->isSuccess) {
            return $this;
        }

        return self::ok($fn($this->value));
    }

    /**
     * Flat map (bind) operation.
     * 
     * @template U
     * @param callable(T): Result<U> $fn
     * @return Result<U>
     */
    public function flatMap(callable $fn): self
    {
        if (!$this->isSuccess) {
            return $this;
        }

        return $fn($this->value);
    }

    /**
     * Map the error if failure.
     * 
     * @param callable $fn
     * @return Result<T>
     */
    public function mapError(callable $fn): self
    {
        if ($this->isSuccess) {
            return $this;
        }

        return self::fail($fn($this->error));
    }

    /**
     * Match pattern for result.
     * 
     * @template U
     * @param callable(T): U $success
     * @param callable(mixed): U $failure
     * @return U
     */
    public function match(callable $success, callable $failure): mixed
    {
        return $this->isSuccess
            ? $success($this->value)
            : $failure($this->error);
    }

    /**
     * Tap into the value without changing the result.
     * 
     * @param callable(T): void $fn
     * @return Result<T>
     */
    public function tap(callable $fn): self
    {
        if ($this->isSuccess) {
            $fn($this->value);
        }

        return $this;
    }

    /**
     * Tap into the error without changing the result.
     * 
     * @param callable(mixed): void $fn
     * @return Result<T>
     */
    public function tapError(callable $fn): self
    {
        if (!$this->isSuccess) {
            $fn($this->error);
        }

        return $this;
    }

    /**
     * Recover from failure with a new result.
     * 
     * @param callable(mixed): Result<T> $fn
     * @return Result<T>
     */
    public function recover(callable $fn): self
    {
        if ($this->isSuccess) {
            return $this;
        }

        return $fn($this->error);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess,
            'value' => $this->value,
            'error' => $this->error,
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Create result from boolean.
     * 
     * @param bool $condition
     * @param mixed $value
     * @param mixed $error
     * @return Result<mixed>
     */
    public static function from(bool $condition, mixed $value = null, mixed $error = null): self
    {
        return $condition ? self::ok($value) : self::fail($error);
    }

    /**
     * Try to execute a callable and wrap result.
     * 
     * @template U
     * @param callable(): U $fn
     * @return Result<U>
     */
    public static function try(callable $fn): self
    {
        try {
            return self::ok($fn());
        } catch (\Throwable $e) {
            return self::fail($e->getMessage());
        }
    }

    /**
     * Combine multiple results into one.
     * 
     * Returns success only if all results are successful.
     * Returns first failure otherwise.
     * 
     * @param array<Result> $results
     * @return Result<array>
     */
    public static function combine(array $results): self
    {
        $values = [];

        foreach ($results as $result) {
            if ($result->isFailure()) {
                return $result;
            }

            $values[] = $result->getValue();
        }

        return self::ok($values);
    }
}
