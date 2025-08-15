<?php

namespace Core\Frontend;

use Core\Application;

/**
 * Class này xử lý việc render một Component lần đầu tiên.
 */
class ComponentRenderer
{
    public function __construct(
        private Application $app,
        private ChecksumService $checksumService,
    ) {
    }

    /**
     * Render một component và trả về HTML hoàn chỉnh với snapshot.
     */
    public function render(string $componentClass, array $attributes = []): string
    {
        /** @var Component $component */
        $component = $this->app->make($componentClass);

        $wireAttributes = [];

        // Gán các thuộc tính ban đầu được truyền vào từ view
        foreach ($attributes as $key => $value) {
            // Tách các thuộc tính 'wire:' đặc biệt ra khỏi các props thông thường
            if (str_starts_with($key, 'wire:')) {
                $wireAttributes[$key] = is_string($value) ? htmlspecialchars($value, ENT_QUOTES) : json_encode($value);
            } else {
                $component->{$key} = $value;
            }
        }

        // Gọi phương thức mount nếu nó tồn tại
        // Điều này cho phép component nhận các props đã được gán
        if (method_exists($component, 'mount')) {
            $this->app->call([$component, 'mount']);
        }

        // Render HTML của component
        $html = $component->render();

        // Tạo ID và snapshot ban đầu
        $id = 'bault-' . bin2hex(random_bytes(10));
        $state = $component->getState();
        $snapshot = [
            'class' => $componentClass,
            'data' => $state,
            'checksum' => $this->checksumService->generate($componentClass, $state),
        ];

        $encodedSnapshot = htmlspecialchars(json_encode($snapshot), ENT_QUOTES);

        // Ghép các thuộc tính wire: vào chuỗi
        $wireAttributesHtml = "wire:id=\"{$id}\" wire:snapshot=\"{$encodedSnapshot}\"";
        foreach ($wireAttributes as $key => $value) {
            $wireAttributesHtml .= " {$key}=\"{$value}\"";
        }

        // Trả về HTML được bao bọc bởi thẻ div gốc của component
        return "<div {$wireAttributesHtml}>{$html}</div>";
    }
}
