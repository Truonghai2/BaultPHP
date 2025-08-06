<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Infrastructure\Models\PageBlock;

class UpdateBlockContentHandler implements CommandHandler
{
    /**
     * Xử lý command cập nhật nội dung block.
     *
     * @param Command|UpdateBlockContentCommand $command
     * @return array<string, mixed> Nội dung cũ của block.
     * @throws PageBlockNotFoundException
     */
    public function handle(Command $command): array
    {
        /** @var UpdateBlockContentCommand $command */
        /** @var PageBlock|null $block */
        $block = PageBlock::find($command->pageBlockId);

        if (!$block) {
            // Hoặc bạn có thể throw một exception tùy chỉnh
            throw new PageBlockNotFoundException("PageBlock with ID {$command->pageBlockId} not found.");
        }

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // BẢO MẬT: Luôn kiểm tra quyền trước khi thực hiện hành động.
        $user->can('update', $block);

        $oldContent = $block->content ?? [];

        $block->content = $command->content;
        $block->save();

        return $oldContent;
    }
}
