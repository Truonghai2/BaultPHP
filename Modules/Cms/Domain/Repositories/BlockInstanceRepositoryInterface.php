<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Repositories;

use Modules\Cms\Domain\Entities\BlockInstance;
use Modules\Cms\Domain\ValueObjects\BlockId;

interface BlockInstanceRepositoryInterface
{
    public function findById(BlockId $id): BlockInstance;

    public function findByIdOrNull(BlockId $id): ?BlockInstance;

    /**
     * @return BlockInstance[]
     */
    public function findByRegion(int $regionId, ?string $contextType = null, ?int $contextId = null): array;

    /**
     * @return BlockInstance[]
     */
    public function findVisibleInRegion(int $regionId, ?string $contextType = null, ?int $contextId = null): array;

    public function save(BlockInstance $blockInstance): void;

    public function delete(BlockId $id): void;

    public function countInRegion(int $regionId): int;

    public function getMaxWeightInRegion(int $regionId): int;

    public function nextId(): BlockId;

    /**
     * @param BlockId[] $ids
     */
    public function reorderByIds(array $ids): void;
}

