<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Entities;

use Modules\Cms\Domain\ValueObjects\PageContent;
use Modules\Cms\Domain\ValueObjects\PageId;
use Modules\Cms\Domain\ValueObjects\Slug;

/**
 * Page Domain Entity
 *
 * Pure domain entity không phụ thuộc vào infrastructure
 */
class Page
{
    private PageId $id;
    private string $name;
    private Slug $slug;
    private ?int $userId;
    private PageContent $content;
    private ?string $featuredImagePath;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        PageId $id,
        string $name,
        Slug $slug,
        ?int $userId = null,
        ?PageContent $content = null,
        ?string $featuredImagePath = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->userId = $userId;
        $this->content = $content ?? PageContent::empty();
        $this->featuredImagePath = $featuredImagePath;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getId(): PageId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): Slug
    {
        return $this->slug;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getContent(): PageContent
    {
        return $this->content;
    }

    public function getFeaturedImagePath(): ?string
    {
        return $this->featuredImagePath;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Update page content
     */
    public function updateContent(PageContent $content): void
    {
        $this->content = $content;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Update featured image
     */
    public function updateFeaturedImage(?string $path): void
    {
        $this->featuredImagePath = $path;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Rename page
     */
    public function rename(string $name, Slug $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Check if user owns this page
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->userId === $userId;
    }

    /**
     * Create from array (for hydration)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new PageId($data['id']),
            $data['name'],
            new Slug($data['slug']),
            $data['user_id'] ?? null,
            isset($data['content']) ? PageContent::fromArray($data['content']) : null,
            $data['featured_image_path'] ?? null,
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
     * Convert to array (for persistence)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'name' => $this->name,
            'slug' => $this->slug->getValue(),
            'user_id' => $this->userId,
            'content' => $this->content->toArray(),
            'featured_image_path' => $this->featuredImagePath,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
