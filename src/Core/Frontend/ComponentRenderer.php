<?php

namespace Core\Frontend;

/**
 * Class này xử lý việc render một Component lần đầu tiên.
 */
class ComponentRenderer
{
    /**
     * Render một component và trả về HTML hoàn chỉnh với snapshot.
     */
    public static function render(string $componentClass, array $attributes = []): string
    {
        /** @var Component $component */
        $component = app($componentClass);

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
        if (method_exists($component, 'mount')) app()->call([$component, 'mount']);

        // Render HTML của component
        $html = $component->render();

        // Tạo ID và snapshot ban đầu
        $id = 'bault-' . bin2hex(random_bytes(10));
        $state = $component->getState();
        $snapshot = [
            'class' => $componentClass,
            'data' => $state,
            'checksum' => self::generateChecksum($componentClass, $state),
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

    /**
     * Tạo checksum để xác thực state.
     */
    private static function generateChecksum(string $class, array $data): string
    {
        // BẢO MẬT: Sắp xếp dữ liệu theo key để đảm bảo chuỗi JSON luôn nhất quán,
        // tránh việc checksum bị sai một cách ngẫu nhiên.
        ksort($data);
        return hash_hmac('sha256', $class . json_encode($data), config('app.key'));
    }
}
