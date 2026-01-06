<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\ValueObjects;

final class RegionName
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Region name cannot be empty');
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException('Region name must be lowercase alphanumeric with hyphens');
        }

        if (strlen($value) > 50) {
            throw new \InvalidArgumentException('Region name cannot exceed 50 characters');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(RegionName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
