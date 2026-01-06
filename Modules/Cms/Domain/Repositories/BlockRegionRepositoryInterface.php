<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Repositories;

use Modules\Cms\Domain\Entities\BlockRegion;
use Modules\Cms\Domain\ValueObjects\RegionName;

interface BlockRegionRepositoryInterface
{
    public function findById(int $id): BlockRegion;

    public function findByIdOrNull(int $id): ?BlockRegion;

    public function findByName(RegionName $name): BlockRegion;

    public function findByNameOrNull(RegionName $name): ?BlockRegion;

    /**
     * @return BlockRegion[]
     */
    public function findAllActive(): array;

    /**
     * @return BlockRegion[]
     */
    public function findAll(): array;

    public function save(BlockRegion $region): void;

    public function delete(int $id): void;

    public function nameExists(RegionName $name, ?int $excludeId = null): bool;
}
