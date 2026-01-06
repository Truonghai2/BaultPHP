<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\ValueObjects;

/**
 * Page ID Value Object
 */
final class PageId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Page ID must be positive');
        }

        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(PageId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}

