<?php

namespace Core\Support;

use Stringable;

/**
 * Một lớp đơn giản để biểu diễn một chuỗi không nên được escape.
 * Điều này rất hữu ích để trả về HTML thô từ các helper để render trong view.
 * Đây là phiên bản core, thay thế cho việc phụ thuộc vào `illuminate/support`.
 */
class HtmlString implements Stringable
{
    /**
     * The HTML string.
     *
     * @var string
     */
    protected string $html;

    /**
     * Create a new HTML string instance.
     *
     * @param  string  $html
     */
    public function __construct(string $html)
    {
        $this->html = $html;
    }

    /**
     * Get the string representation of the object.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->html;
    }
}
