<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Entities;

use Modules\Cms\Domain\ValueObjects\BlockConfiguration;
use Modules\Cms\Domain\ValueObjects\BlockId;

class BlockInstance
{
    private BlockId $id;
    private int $blockTypeId;
    private int $regionId;
    private string $contextType;
    private ?int $contextId;
    private ?string $title;
    private BlockConfiguration $config;
    private ?string $content;
    private int $weight;
    private bool $visible;
    private string $visibilityMode;
    private ?array $visibilityRules;
    private ?array $allowedRoles;
    private ?array $deniedRoles;
    private ?int $createdBy;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        BlockId $id,
        int $blockTypeId,
        int $regionId,
        string $contextType = 'global',
        ?int $contextId = null,
        ?string $title = null,
        ?BlockConfiguration $config = null,
        ?string $content = null,
        int $weight = 0,
        bool $visible = true,
        string $visibilityMode = 'show',
        ?array $visibilityRules = null,
        ?array $allowedRoles = null,
        ?array $deniedRoles = null,
        ?int $createdBy = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->blockTypeId = $blockTypeId;
        $this->regionId = $regionId;
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->title = $title;
        $this->config = $config ?? BlockConfiguration::empty();
        $this->content = $content;
        $this->weight = $weight;
        $this->visible = $visible;
        $this->visibilityMode = $visibilityMode;
        $this->visibilityRules = $visibilityRules;
        $this->allowedRoles = $allowedRoles;
        $this->deniedRoles = $deniedRoles;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getId(): BlockId
    {
        return $this->id;
    }

    public function getBlockTypeId(): int
    {
        return $this->blockTypeId;
    }

    public function getRegionId(): int
    {
        return $this->regionId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getConfig(): BlockConfiguration
    {
        return $this->config;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function getContextType(): string
    {
        return $this->contextType;
    }

    public function getContextId(): ?int
    {
        return $this->contextId;
    }

    public function updateContent(string $content): void
    {
        $this->content = $content;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateConfiguration(BlockConfiguration $config): void
    {
        $this->config = $config;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateTitle(string $title): void
    {
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function moveUp(): void
    {
        if ($this->weight > 0) {
            $this->weight--;
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function moveDown(): void
    {
        $this->weight++;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function show(): void
    {
        $this->visible = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function hide(): void
    {
        $this->visible = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toggleVisibility(): void
    {
        $this->visible = !$this->visible;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function moveToRegion(int $regionId): void
    {
        $this->regionId = $regionId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isVisibleTo(?array $userRoles = null): bool
    {
        if (!$this->visible) {
            return false;
        }

        if ($userRoles === null) {
            $userRoles = ['guest'];
        }

        if ($this->deniedRoles && count(array_intersect($userRoles, $this->deniedRoles)) > 0) {
            return false;
        }

        if ($this->allowedRoles && count(array_intersect($userRoles, $this->allowedRoles)) === 0) {
            return false;
        }

        return true;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            new BlockId($data['id']),
            $data['block_type_id'],
            $data['region_id'],
            $data['context_type'] ?? 'global',
            $data['context_id'] ?? null,
            $data['title'] ?? null,
            isset($data['config']) ? BlockConfiguration::fromArray($data['config']) : null,
            $data['content'] ?? null,
            $data['weight'] ?? 0,
            (bool)($data['visible'] ?? true),
            $data['visibility_mode'] ?? 'show',
            $data['visibility_rules'] ?? null,
            $data['allowed_roles'] ?? null,
            $data['denied_roles'] ?? null,
            $data['created_by'] ?? null,
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'block_type_id' => $this->blockTypeId,
            'region_id' => $this->regionId,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'title' => $this->title,
            'config' => $this->config->toArray(),
            'content' => $this->content,
            'weight' => $this->weight,
            'visible' => $this->visible,
            'visibility_mode' => $this->visibilityMode,
            'visibility_rules' => $this->visibilityRules,
            'allowed_roles' => $this->allowedRoles,
            'denied_roles' => $this->deniedRoles,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}

