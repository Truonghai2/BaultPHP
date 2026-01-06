<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Entities;

use Modules\Cms\Domain\ValueObjects\PageBlockId;

/**
 * PageBlock Domain Entity
 * 
 * Represents a content block within a page (page editor system)
 */
class PageBlock
{
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        private PageBlockId $id,
        private int $pageId,
        private string $componentClass,
        private int $sortOrder,
        private array $content = []
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(
        PageBlockId $id,
        int $pageId,
        string $componentClass,
        int $sortOrder
    ): self {
        return new self($id, $pageId, $componentClass, $sortOrder);
    }

    public function getId(): PageBlockId
    {
        return $this->id;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getComponentClass(): string
    {
        return $this->componentClass;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function updateContent(array $content): void
    {
        $this->content = $content;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new \DomainException("Sort order must be non-negative");
        }
        $this->sortOrder = $sortOrder;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function incrementOrder(): void
    {
        $this->sortOrder++;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function decrementOrder(): void
    {
        if ($this->sortOrder > 0) {
            $this->sortOrder--;
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function duplicate(PageBlockId $newId): self
    {
        return new self(
            $newId,
            $this->pageId,
            $this->componentClass,
            $this->sortOrder + 1,
            $this->content
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'page_id' => $this->pageId,
            'component_class' => $this->componentClass,
            'sort_order' => $this->sortOrder,
            'content' => $this->content,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        $block = new self(
            new PageBlockId($data['id']),
            $data['page_id'],
            $data['component_class'],
            $data['sort_order'] ?? $data['order'] ?? 0,
            $data['content'] ?? []
        );

        if (isset($data['created_at'])) {
            $block->createdAt = new \DateTimeImmutable($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $block->updatedAt = new \DateTimeImmutable($data['updated_at']);
        }

        return $block;
    }
}

