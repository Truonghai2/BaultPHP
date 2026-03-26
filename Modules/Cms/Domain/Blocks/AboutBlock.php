<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * About Block – Trang Giới thiệu
 * Hiển thị intro, sứ mệnh, giá trị (đồng bộ theme gray-900 + indigo/purple).
 */
class AboutBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'about';
    }

    public function getTitle(): string
    {
        return 'About Section';
    }

    public function getDescription(): string
    {
        return 'Giới thiệu công ty: intro, sứ mệnh, giá trị cốt lõi';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getIcon(): string
    {
        return '📄';
    }

    public function getDefaultConfig(): array
    {
        return [
            'title' => 'Về chúng tôi',
            'subtitle' => 'Xây dựng sản phẩm chất lượng với công nghệ hiện đại',
            'intro' => 'BaultPHP là framework PHP hiện đại, tập trung hiệu năng và trải nghiệm nhà phát triển. Chúng tôi tin vào mã nguồn sạch, kiến trúc rõ ràng và công cụ hỗ trợ tối ưu.',
            'mission_title' => 'Sứ mệnh',
            'mission_text' => 'Mang đến cho cộng đồng PHP một bộ khung ứng dụng mạnh mẽ, dễ mở rộng và thân thiện với SPA, API và triển khai trên môi trường hiệu năng cao.',
            'values' => [
                ['title' => 'Chất lượng', 'text' => 'Code sạch, kiến trúc rõ ràng, dễ bảo trì.', 'icon' => '✓'],
                ['title' => 'Hiệu năng', 'text' => 'Tối ưu cho Swoole, Redis, cache và scale ngang.', 'icon' => '⚡'],
                ['title' => 'Cộng đồng', 'text' => 'Mã nguồn mở, tài liệu đầy đủ, hỗ trợ tích cực.', 'icon' => '👥'],
            ],
        ];
    }

    public function render(array $config = [], ?array $context = null): string
    {
        $config = array_merge($this->getDefaultConfig(), $config);
        $content = $context['block_info']['content'] ?? null;
        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
            }
        }
        return $this->renderView('cms::blocks.about', $config);
    }
}
