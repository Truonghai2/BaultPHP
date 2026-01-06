<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\ValueObjects;

/**
 * PageBlock ID Value Object
 */
final class PageBlockId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException("PageBlock ID must be positive");
        }
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}

