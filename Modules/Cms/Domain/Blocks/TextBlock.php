<?php

namespace Modules\Cms\Domain\Blocks;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\User\Infrastructure\Models\User;

/**
 * Text Block
 * 
 * Block đơn giản để hiển thị text/HTML content
 */
class TextBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'text';
    }

    public function getTitle(): string
    {
        return 'Text Block';
    }

    public function getDescription(): string
    {
        return 'A simple block for displaying text or HTML content';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getIcon(): ?string
    {
        return 'fa-align-left';
    }

    public function getDefaultConfig(): array
    {
        return [
            'format' => 'html', // html, markdown, plain
            'show_title' => true,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'format' => [
                'type' => 'select',
                'label' => 'Content Format',
                'options' => [
                    'html' => 'HTML',
                    'markdown' => 'Markdown',
                    'plain' => 'Plain Text',
                ],
                'default' => 'html',
            ],
            'show_title' => [
                'type' => 'checkbox',
                'label' => 'Show Block Title',
                'default' => true,
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        
        $content = $context['content'] ?? '';
        $format = $config['format'] ?? 'html';
        
        // Process content based on format
        $processedContent = match ($format) {
            'markdown' => $this->renderMarkdown($content),
            'plain' => $this->escapeHtml($content),
            default => $content,
        };
        
        return $this->renderView('cms::blocks.text', array_merge($config, [
            'title' => $context['title'] ?? '',
            'content' => $processedContent,
        ]));
    }

    protected function renderMarkdown(string $content): string
    {
        // TODO: Integrate markdown parser (e.g., league/commonmark)
        // For now, return as plain text
        return '<p>' . nl2br($this->escapeHtml($content)) . '</p>';
    }

    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (isset($config['format']) && !in_array($config['format'], ['html', 'markdown', 'plain'])) {
            $errors['format'] = 'Invalid format';
        }

        return $errors;
    }
}

