<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Entities;

use Modules\Cms\Domain\ValueObjects\RegionName;

class BlockRegion
{
    private int $id;
    private RegionName $name;
    private string $title;
    private ?string $description;
    private int $maxBlocks;
    private bool $isActive;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        int $id,
        RegionName $name,
        string $title,
        ?string $description = null,
        int $maxBlocks = 10,
        bool $isActive = true,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
        $this->maxBlocks = $maxBlocks;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): RegionName
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMaxBlocks(): int
    {
        return $this->maxBlocks;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateMaxBlocks(int $maxBlocks): void
    {
        if ($maxBlocks <= 0) {
            throw new \InvalidArgumentException('Max blocks must be positive');
        }

        $this->maxBlocks = $maxBlocks;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function canAddBlock(int $currentBlockCount): bool
    {
        return $currentBlockCount < $this->maxBlocks;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            new RegionName($data['name']),
            $data['title'],
            $data['description'] ?? null,
            $data['max_blocks'] ?? 10,
            (bool)($data['is_active'] ?? true),
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name->getValue(),
            'title' => $this->title,
            'description' => $this->description,
            'max_blocks' => $this->maxBlocks,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
