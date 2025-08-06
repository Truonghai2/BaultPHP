<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Infrastructure\Models\PageBlock;

class DuplicateBlockHandler implements CommandHandler
{
    /**
     * Xử lý command sao chép một block.
     *
     * @param Command|DuplicateBlockCommand $command
     * @return PageBlock
     * @throws PageBlockNotFoundException|\App\Exceptions\AuthorizationException
     */
    public function handle(Command $command): PageBlock
    {
        /** @var DuplicateBlockCommand $command */
        /** @var PageBlock|null $originalBlock */
        $originalBlock = PageBlock::find($command->pageBlockId);

        if (!$originalBlock) {
            throw new PageBlockNotFoundException("PageBlock with ID {$command->pageBlockId} not found.");
        }

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // Sử dụng PageBlockPolicy để kiểm tra quyền sao chép một cách tường minh.
        // Phương thức `can` sẽ ném AuthorizationException nếu thất bại.
        $user->can('duplicate', $originalBlock);

        // 1. Tạo vị trí cho block mới bằng cách dịch chuyển các block phía sau.
        PageBlock::where('page_id', '=', $originalBlock->page_id)
                 ->where('order', '>', $originalBlock->order)
                 ->increment('order');

        // 2. Sao chép block và gán thứ tự mới.
        $newBlock = $originalBlock->replicate();
        $newBlock->order = $originalBlock->order + 1;
        $newBlock->save();

        return $newBlock;
    }
}
