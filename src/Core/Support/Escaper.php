<?php

namespace Core\Support;

use Laminas\Escaper\Escaper as LaminasEscaper;

/**
 * Lớp Core Escaper để đóng gói logic bảo mật XSS.
 *
 * Lớp này hoạt động như một trình bao (wrapper) cho một thư viện escaping mạnh mẽ (mặc định là laminas-escaper).
 * Việc này giúp cho hàm `esc()` trong helpers luôn gọi đến một API nhất quán của core,
 * và cho phép thay đổi thư viện bên dưới trong tương lai mà không cần sửa đổi code ở nhiều nơi.
 */
class Escaper
{
    /**
     * The underlying escaper instance.
     * @var LaminasEscaper|null
     */
    protected ?LaminasEscaper $laminasEscaper = null;

    public function __construct()
    {
        if (class_exists(LaminasEscaper::class)) {
            $this->laminasEscaper = new LaminasEscaper('utf-8');
        }
    }

    /**
     * Escape a string for a specific context.
     *
     * @param string|null $value
     * @param string $context
     * @return string
     */
    public function escape(?string $value, string $context = 'html'): string
    {
        if ($value === null) {
            return '';
        }

        if ($this->laminasEscaper) {
            return match ($context) {
                'js' => $this->laminasEscaper->escapeJs($value),
                'css' => $this->laminasEscaper->escapeCss($value),
                'url' => $this->laminasEscaper->escapeUrl($value),
                'attr' => $this->laminasEscaper->escapeHtmlAttr($value),
                default => $this->laminasEscaper->escapeHtml($value),
            };
        }

        // Fallback to basic HTML escaping if laminas-escaper is not available.
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
