<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Blocks;

/**
 * Contact Block – Trang Liên hệ
 * Thông tin liên hệ + form gửi tin nhắn (theme tối đồng bộ).
 */
class ContactBlock extends AbstractBlock
{
    public function getName(): string
    {
        return 'contact';
    }

    public function getTitle(): string
    {
        return 'Contact Section';
    }

    public function getDescription(): string
    {
        return 'Liên hệ: địa chỉ, email, điện thoại và form gửi tin nhắn';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getIcon(): string
    {
        return '✉️';
    }

    public function getDefaultConfig(): array
    {
        return [
            'title' => 'Liên hệ với chúng tôi',
            'subtitle' => 'Gửi tin nhắn hoặc dùng thông tin bên dưới',
            'address' => 'Việt Nam',
            'email' => 'hello@bault.dev',
            'phone' => '',
            'form_action' => '', // route('contact.submit') khi có route
            'show_form' => true,
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
        if (empty($config['form_action']) && function_exists('route_exists') && route_exists('contact.submit')) {
            $config['form_action'] = route('contact.submit');
        }
        return $this->renderView('cms::blocks.contact', $config);
    }
}
