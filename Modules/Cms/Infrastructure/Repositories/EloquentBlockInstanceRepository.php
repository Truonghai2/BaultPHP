<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Repositories;

use Modules\Cms\Domain\Entities\BlockInstance as BlockInstanceEntity;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Domain\Repositories\BlockInstanceRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\BlockId;
use Modules\Cms\Infrastructure\Models\BlockInstance as BlockInstanceModel;

class EloquentBlockInstanceRepository implements BlockInstanceRepositoryInterface
{
    public function findById(BlockId $id): BlockInstanceEntity
    {
        $model = BlockInstanceModel::find($id->getValue());

        if (!$model) {
            throw new PageBlockNotFoundException("Block instance with ID {$id->getValue()} not found");
        }

        return $this->toDomain($model);
    }

    public function findByIdOrNull(BlockId $id): ?BlockInstanceEntity
    {
        $model = BlockInstanceModel::find($id->getValue());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByRegion(int $regionId, ?string $contextType = null, ?int $contextId = null): array
    {
        $query = BlockInstanceModel::where('region_id', $regionId)
            ->orderBy('weight', 'asc');

        if ($contextType !== null) {
            $query->where('context_type', $contextType);
        }

        if ($contextId !== null) {
            $query->where('context_id', $contextId);
        }

        return $query->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function findVisibleInRegion(int $regionId, ?string $contextType = null, ?int $contextId = null): array
    {
        $query = BlockInstanceModel::where('region_id', $regionId)
            ->where('visible', true)
            ->orderBy('weight', 'asc');

        if ($contextType !== null) {
            $query->where('context_type', $contextType);
        }

        if ($contextId !== null) {
            $query->where('context_id', $contextId);
        }

        return $query->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function save(BlockInstanceEntity $blockInstance): void
    {
        $data = $blockInstance->toArray();
        $id = $data['id'];

        $model = BlockInstanceModel::find($id);

        if ($model) {
            $model->update($data);
        } else {
            BlockInstanceModel::create($data);
        }
    }

    public function delete(BlockId $id): void
    {
        BlockInstanceModel::where('id', $id->getValue())->delete();
    }

    public function countInRegion(int $regionId): int
    {
        return BlockInstanceModel::where('region_id', $regionId)->count();
    }

    public function getMaxWeightInRegion(int $regionId): int
    {
        return BlockInstanceModel::where('region_id', $regionId)->max('weight') ?? 0;
    }

    public function nextId(): BlockId
    {
        $lastId = BlockInstanceModel::max('id') ?? 0;
        return new BlockId($lastId + 1);
    }

    public function reorderByIds(array $ids): void
    {
        foreach ($ids as $index => $id) {
            if ($id instanceof BlockId) {
                $id = $id->getValue();
            }

            BlockInstanceModel::where('id', $id)->update(['weight' => $index]);
        }
    }

    private function toDomain(BlockInstanceModel $model): BlockInstanceEntity
    {
        return BlockInstanceEntity::fromArray([
            'id' => $model->id,
            'block_type_id' => $model->block_type_id,
            'region_id' => $model->region_id,
            'context_type' => $model->context_type,
            'context_id' => $model->context_id,
            'title' => $model->title,
            'config' => $model->config ?? [],
            'content' => $model->content,
            'weight' => $model->weight,
            'visible' => $model->visible,
            'visibility_mode' => $model->visibility_mode,
            'visibility_rules' => $model->visibility_rules,
            'allowed_roles' => $model->allowed_roles,
            'denied_roles' => $model->denied_roles,
            'created_by' => $model->created_by,
            'created_at' => $model->created_at?->toDateTimeString(),
            'updated_at' => $model->updated_at?->toDateTimeString(),
        ]);
    }
}

