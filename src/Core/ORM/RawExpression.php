<?php

namespace Core\ORM;

/**
 * Represents a raw SQL expression that should not be escaped or bound as a parameter.
 */
class RawExpression
{
    /**
     * The raw expression value.
     *
     * @var string
     */
    protected string $value;

    /**
     * Create a new raw expression instance.
     *
     * @param  string  $value
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Get the value of the expression.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the value of the expression when casting to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getValue();
    }
}
