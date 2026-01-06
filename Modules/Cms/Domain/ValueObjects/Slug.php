<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\ValueObjects;

/**
 * Slug Value Object
 */
final class Slug
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
            throw new \InvalidArgumentException('Slug cannot be empty');
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException('Slug must be lowercase alphanumeric with hyphens');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Slug cannot exceed 255 characters');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Slug $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Generate slug from string
     */
    public static function fromString(string $string): self
    {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return new self($slug);
    }
}
