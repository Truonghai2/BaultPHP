<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Repositories;

use Modules\Cms\Domain\Entities\Page as PageEntity;
use Modules\Cms\Domain\Exceptions\PageNotFoundException;
use Modules\Cms\Domain\Repositories\PageRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\PageId;
use Modules\Cms\Domain\ValueObjects\Slug;
use Modules\Cms\Infrastructure\Models\Page as PageModel;

/**
 * Eloquent Page Repository
 *
 * Implementation of the repository using Eloquent
 * Convert between Domain Entities and Infrastructure Models
 */
class EloquentPageRepository implements PageRepositoryInterface
{
    public function findById(PageId $id): PageEntity
    {
        $model = PageModel::find($id->getValue());

        if (!$model) {
            throw new PageNotFoundException("Page with ID {$id->getValue()} not found");
        }

        return $this->toDomain($model);
    }

    public function findByIdOrNull(PageId $id): ?PageEntity
    {
        $model = PageModel::find($id->getValue());

        return $model ? $this->toDomain($model) : null;
    }

    public function findBySlug(Slug $slug): PageEntity
    {
        $model = PageModel::where('slug', $slug->getValue())->first();

        if (!$model) {
            throw new PageNotFoundException("Page with slug '{$slug->getValue()}' not found");
        }

        return $this->toDomain($model);
    }

    public function slugExists(Slug $slug, ?PageId $excludeId = null): bool
    {
        $query = PageModel::where('slug', $slug->getValue());

        if ($excludeId) {
            $query->where('id', '!=', $excludeId->getValue());
        }

        return $query->exists();
    }

    public function getAll(): array
    {
        return PageModel::all()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    public function getByUserId(int $userId): array
    {
        return PageModel::where('user_id', $userId)
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    public function save(PageEntity $page): void
    {
        $data = $page->toArray();
        $id = $data['id'];

        $model = PageModel::find($id);

        if ($model) {
            $model->update($data);
        } else {
            PageModel::create($data);
        }
    }

    public function delete(PageId $id): void
    {
        $model = PageModel::find($id->getValue());

        if ($model) {
            $model->delete();
        }
    }

    public function nextId(): PageId
    {
        $lastId = PageModel::max('id') ?? 0;
        return new PageId($lastId + 1);
    }

    /**
     * Convert Eloquent Model to Domain Entity
     */
    private function toDomain(PageModel $model): PageEntity
    {
        return PageEntity::fromArray([
            'id' => $model->id,
            'name' => $model->name,
            'slug' => $model->slug,
            'user_id' => $model->user_id,
            'content' => $model->content ?? [],
            'featured_image_path' => $model->featured_image_path,
            'created_at' => $model->created_at?->toDateTimeString(),
            'updated_at' => $model->updated_at?->toDateTimeString(),
        ]);
    }
}
