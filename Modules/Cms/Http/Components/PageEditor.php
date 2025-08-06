<?php

namespace Modules\Cms\Http\Components;

use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\Component;
use Core\Frontend\Concerns\WithFileUploads;
use Modules\Cms\Application\Commands\UpdatePageContentCommand;
use Modules\Cms\Domain\Entities\Page;

/**
 * Component để chỉnh sửa nội dung của một trang trong CMS.
 */
class PageEditor extends Component
{
    use WithFileUploads;

    public Page $page;
    public array $blocks = [];
    public string $newBlockType = 'text';

    public $featuredImage; // Thuộc tính để lưu file upload

    /**
     * Định nghĩa các quy tắc validation cho các thuộc tính, bao gồm cả dữ liệu lồng nhau.
     * 'blocks.*.content' sẽ áp dụng các quy tắc cho trường 'content' của MỌI phần tử trong mảng 'blocks'.
     */
    protected array $rules = [
        'blocks.*.content' => 'required|min:3',
        'featuredImage' => 'nullable|string', // Ví dụ rule cho trường khác
    ];

    /** Tùy chọn: Tùy chỉnh thông báo lỗi cho các trường lồng nhau. */
    protected array $messages = [
        'blocks.*.content.required' => 'Nội dung của khối này không được để trống.',
        'blocks.*.content.min' => 'Nội dung của khối phải có ít nhất :min ký tự.',
    ];
    /**
     * Phương thức mount() được gọi khi component được khởi tạo lần đầu.
     * Nó nhận các tham số từ directive @component.
     */
    public function mount(Page $page): void
    {
        $this->page = $page;
        $this->blocks = $page->content['blocks'] ?? [];
        $this->featuredImage = $page->featured_image_path; // Load ảnh đã có
    }

    #[CallableMethod]
    public function addBlock(): void
    {
        $this->blocks[] = [
            'type' => $this->newBlockType,
            'content' => '',
        ];
    }

    #[CallableMethod]
    public function removeBlock(int $index): void
    {
        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks); // Sắp xếp lại key của mảng
    }

    #[CallableMethod]
    public function save(): void
    {
        // Kích hoạt validation. Nếu thất bại, một exception sẽ được throw và ComponentController sẽ xử lý.
        $this->validate();

        $featuredImagePath = $this->page->featured_image_path;

        // Nếu có file mới được upload, lưu nó và lấy đường dẫn
        if ($this->featuredImage && method_exists($this->featuredImage, 'store')) {
            $featuredImagePath = $this->featuredImage->store('page-images', 'local');
        }

        // Sử dụng helper dispatchCommand đã có trong base Component
        $this->dispatchCommand(new UpdatePageContentCommand($this->page->id, $this->blocks, $featuredImagePath));

        // Có thể thêm một event để thông báo thành công ra frontend
        // $this->dispatchBrowserEvent('notify', ['message' => 'Page saved successfully!']);
    }

    public function render()
    {
        // Render view từ trong Module CMS
        return view('cms::components.page-editor');
    }

    /**
     * Lifecycle hook này được gọi tự động mỗi khi một thuộc tính public được cập nhật từ frontend.
     * Ví dụ: khi người dùng rời khỏi một trường input có wire:model.lazy.
     *
     * @param string $propertyName Tên của thuộc tính đã được cập nhật (ví dụ: 'blocks.0.content')
     */
    public function updated(string $propertyName): void
    {
        $this->validateOnly($propertyName);
    }
}
