<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Repositories;

use Modules\Cms\Domain\Entities\PageBlock as PageBlockEntity;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Domain\Repositories\PageBlockRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\PageBlockId;
use Modules\Cms\Infrastructure\Models\PageBlock as PageBlockModel;

class EloquentPageBlockRepository implements PageBlockRepositoryInterface
{
    public function findById(PageBlockId $id): PageBlockEntity
    {
        $model = PageBlockModel::find($id->getValue());

        if (!$model) {
            throw new PageBlockNotFoundException("PageBlock with ID {$id->getValue()} not found");
        }

        return $this->toDomain($model);
    }

    public function findByIdOrNull(PageBlockId $id): ?PageBlockEntity
    {
        $model = PageBlockModel::find($id->getValue());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByPageId(int $pageId): array
    {
        return PageBlockModel::where('page_id', $pageId)
            ->orderBy('order', 'asc')
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    public function countByPageId(int $pageId): int
    {
        return PageBlockModel::where('page_id', $pageId)->count();
    }

    public function save(PageBlockEntity $block): void
    {
        $data = $block->toArray();
        $id = $data['id'];

        $model = PageBlockModel::find($id);

        if ($model) {
            $model->update($data);
        } else {
            PageBlockModel::create($data);
        }
    }

    public function delete(PageBlockId $id): void
    {
        PageBlockModel::where('id', $id->getValue())->delete();
    }

    public function nextId(): PageBlockId
    {
        $lastId = PageBlockModel::max('id') ?? 0;
        return new PageBlockId($lastId + 1);
    }

    public function incrementOrdersAfter(int $pageId, int $order): void
    {
        PageBlockModel::where('page_id', $pageId)
            ->where('order', '>=', $order)
            ->increment('order');
    }

    public function decrementOrdersAfter(int $pageId, int $order): void
    {
        PageBlockModel::where('page_id', $pageId)
            ->where('order', '>', $order)
            ->decrement('order');
    }

    public function updateOrders(array $orderMap): void
    {
        foreach ($orderMap as $blockId => $order) {
            PageBlockModel::where('id', $blockId)->update(['order' => $order]);
        }
    }

    private function toDomain(PageBlockModel $model): PageBlockEntity
    {
        return PageBlockEntity::fromArray([
            'id' => $model->id,
            'page_id' => $model->page_id,
            'component_class' => $model->component_class,
            'order' => $model->order,
            'content' => $model->content ?? [],
            'created_at' => $model->created_at?->toDateTimeString(),
            'updated_at' => $model->updated_at?->toDateTimeString(),
        ]);
    }
}
