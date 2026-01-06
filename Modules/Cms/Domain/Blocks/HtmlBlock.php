<?php

namespace Modules\Cms\Domain\Blocks;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\User\Infrastructure\Models\User;

/**
 * HTML Block
 *
 * Block để hiển thị raw HTML (cho advanced users)
 */
class HtmlBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'html';
    }

    public function getTitle(): string
    {
        return 'HTML Block';
    }

    public function getDescription(): string
    {
        return 'A block for displaying raw HTML code (requires html permission)';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getIcon(): ?string
    {
        return 'fa-code';
    }

    public function getDefaultConfig(): array
    {
        return [
            'wrap_in_div' => true,
            'custom_class' => '',
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'wrap_in_div' => [
                'type' => 'checkbox',
                'label' => 'Wrap content in div',
                'default' => true,
            ],
            'custom_class' => [
                'type' => 'text',
                'label' => 'Custom CSS class',
                'placeholder' => 'my-custom-class',
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);

        return $this->renderView('cms::blocks.html', [
            'html' => $context['content'] ?? '',
            'wrapInDiv' => $config['wrap_in_div'] ?? true,
            'customClass' => $config['custom_class'] ?? '',
        ]);
    }

    public function canAdd(User $user): bool
    {
        // Chỉ cho phép users có permission 'cms.blocks.html'
        return $user->can('cms.blocks.html');
    }

    public function canEdit(User $user, BlockInstance $instance): bool
    {
        return $user->can('cms.blocks.html');
    }
}
