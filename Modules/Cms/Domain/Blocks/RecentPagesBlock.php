<?php

namespace Modules\Cms\Domain\Blocks;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\User\Infrastructure\Models\User;

/**
 * Recent Pages Block
 * 
 * Block hiển thị các pages mới nhất
 */
class RecentPagesBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'recent_pages';
    }

    public function getTitle(): string
    {
        return 'Recent Pages';
    }

    public function getDescription(): string
    {
        return 'Display a list of recently created or updated pages';
    }

    public function getCategory(): string
    {
        return 'widget';
    }

    public function getIcon(): ?string
    {
        return 'fa-clock';
    }

    public function getDefaultConfig(): array
    {
        return [
            'limit' => 5,
            'order_by' => 'created_at', // created_at, updated_at
            'show_date' => true,
            'show_author' => false,
            'excerpt_length' => 100,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'limit' => [
                'type' => 'number',
                'label' => 'Number of pages to show',
                'default' => 5,
                'min' => 1,
                'max' => 20,
            ],
            'order_by' => [
                'type' => 'select',
                'label' => 'Order by',
                'options' => [
                    'created_at' => 'Creation Date',
                    'updated_at' => 'Last Modified Date',
                ],
                'default' => 'created_at',
            ],
            'show_date' => [
                'type' => 'checkbox',
                'label' => 'Show Date',
                'default' => true,
            ],
            'show_author' => [
                'type' => 'checkbox',
                'label' => 'Show Author',
                'default' => false,
            ],
            'excerpt_length' => [
                'type' => 'number',
                'label' => 'Excerpt length (0 to hide)',
                'default' => 100,
                'min' => 0,
                'max' => 500,
            ],
        ];
    }

    /**
     * Preload data for all RecentPagesBlock instances on the page.
     * This is more efficient as it can be optimized, but for this simple block,
     * we still query per block config. A more advanced implementation could group queries.
     */
    public function preloadData(\Core\Support\Collection $blocks): array
    {
        $data = [];
        foreach ($blocks as $block) {
            $config = array_merge($this->getDefaultConfig(), $block->getConfig());
            $limit = $config['limit'] ?? 5;
            $orderBy = $config['order_by'] ?? 'created_at';

            $pages = Page::orderBy($orderBy, 'desc')
                ->limit($limit)
                ->get();
            
            $data[$block->id] = ['pages' => $pages];
        }
        return $data;
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        return $this->renderView('cms::blocks.recent-pages', array_merge($config, [
            'pages' => $context['preloaded']['pages'] ?? [],
        ]));
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheLifetime(): int
    {
        return 300; // 5 minutes
    }
}
