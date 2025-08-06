<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;

class RestoreBlockHandler implements CommandHandler
{
    /**
     * Xử lý command khôi phục một block đã bị xóa.
     *
     * @param Command|RestoreBlockCommand $command
     * @return void
     */
    public function handle(Command $command): void
    {
        /** @var RestoreBlockCommand $command */

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // BẢO MẬT: Cần kiểm tra xem người dùng có quyền cập nhật trang
        // mà họ đang cố gắng khôi phục block vào hay không.
        $page = Page::find($command->pageId);
        if ($page) {
            $user->can('update', $page);
        }

        // 1. Dịch chuyển các block khác xuống để tạo không gian.
        PageBlock::where('page_id', '=', $command->pageId)
                 ->where('order', '>=', $command->order)
                 ->increment('order');

        // 2. Tạo lại block với dữ liệu và thứ tự ban đầu.
        PageBlock::create([
            'page_id' => $command->pageId,
            'component_class' => $command->componentClass,
            'content' => $command->content,
            'order' => $command->order,
        ]);
    }
}
