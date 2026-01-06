<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\ValueObjects;

/**
 * Page Content Value Object
 *
 * Encapsulates page content structure
 */
final class PageContent
{
    private array $blocks;

    public function __construct(array $blocks = [])
    {
        $this->blocks = $blocks;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function addBlock(array $block): self
    {
        $blocks = $this->blocks;
        $blocks[] = $block;

        return new self($blocks);
    }

    public function removeBlock(int $index): self
    {
        $blocks = $this->blocks;
        unset($blocks[$index]);

        return new self(array_values($blocks));
    }

    public function updateBlock(int $index, array $block): self
    {
        $blocks = $this->blocks;
        $blocks[$index] = $block;

        return new self($blocks);
    }

    public function isEmpty(): bool
    {
        return empty($this->blocks);
    }

    public function count(): int
    {
        return count($this->blocks);
    }

    public function toArray(): array
    {
        return ['blocks' => $this->blocks];
    }

    public static function fromArray(array $data): self
    {
        return new self($data['blocks'] ?? []);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function equals(PageContent $other): bool
    {
        return $this->blocks === $other->blocks;
    }
}
