<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Repositories;

use Modules\Cms\Domain\Entities\BlockRegion as BlockRegionEntity;
use Modules\Cms\Domain\Repositories\BlockRegionRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\RegionName;
use Modules\Cms\Infrastructure\Models\BlockRegion as BlockRegionModel;

class EloquentBlockRegionRepository implements BlockRegionRepositoryInterface
{
    public function findById(int $id): BlockRegionEntity
    {
        $model = BlockRegionModel::find($id);

        if (!$model) {
            throw new \DomainException("Block region with ID {$id} not found");
        }

        return $this->toDomain($model);
    }

    public function findByIdOrNull(int $id): ?BlockRegionEntity
    {
        $model = BlockRegionModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByName(RegionName $name): BlockRegionEntity
    {
        $model = BlockRegionModel::where('name', $name->getValue())->first();

        if (!$model) {
            throw new \DomainException("Block region with name '{$name->getValue()}' not found");
        }

        return $this->toDomain($model);
    }

    public function findByNameOrNull(RegionName $name): ?BlockRegionEntity
    {
        $model = BlockRegionModel::where('name', $name->getValue())->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAllActive(): array
    {
        return BlockRegionModel::where('is_active', true)
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function findAll(): array
    {
        return BlockRegionModel::all()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function save(BlockRegionEntity $region): void
    {
        $data = $region->toArray();
        $id = $data['id'];

        $model = BlockRegionModel::find($id);

        if ($model) {
            $model->update($data);
        } else {
            BlockRegionModel::create($data);
        }
    }

    public function delete(int $id): void
    {
        BlockRegionModel::where('id', $id)->delete();
    }

    public function nameExists(RegionName $name, ?int $excludeId = null): bool
    {
        $query = BlockRegionModel::where('name', $name->getValue());

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomain(BlockRegionModel $model): BlockRegionEntity
    {
        return BlockRegionEntity::fromArray([
            'id' => $model->id,
            'name' => $model->name,
            'title' => $model->title,
            'description' => $model->description,
            'max_blocks' => $model->max_blocks,
            'is_active' => $model->is_active,
            'created_at' => $model->created_at?->toDateTimeString(),
            'updated_at' => $model->updated_at?->toDateTimeString(),
        ]);
    }
}

