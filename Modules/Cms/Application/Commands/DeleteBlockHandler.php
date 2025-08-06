<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Infrastructure\Models\PageBlock;

class DeleteBlockHandler implements CommandHandler
{
    /**
     * Xử lý command xóa một block.
     *
     * @param Command|DeleteBlockCommand $command
     * @return PageBlock Trả về block đã bị xóa để có thể undo.
     * @throws PageBlockNotFoundException|\App\Exceptions\AuthorizationException
     */
    public function handle(Command $command): PageBlock
    {
        /** @var DeleteBlockCommand $command */
        /** @var PageBlock|null $block */
        $block = PageBlock::find($command->pageBlockId);

        if (!$block) {
            throw new PageBlockNotFoundException("PageBlock with ID {$command->pageBlockId} not found.");
        }

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // Kiểm tra quyền. Phương thức `can` sẽ ném AuthorizationException nếu thất bại.
        $user->can('delete', $block);

        // Cập nhật lại thứ tự của các block còn lại trên cùng một trang.
        PageBlock::where('page_id', '=', $block->page_id)
                 ->where('order', '>', $block->order)
                 ->decrement('order');

        $block->delete();

        return $block;
    }
}
