<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use App\Exceptions\AuthorizationException;
use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Exceptions\PageNotFoundException;
use Modules\Cms\Infrastructure\Models\Page;

class UpdatePageContentCommandHandler implements CommandHandler
{
    /**
     * Xử lý command cập nhật nội dung và ảnh đại diện của trang.
     *
     * @param UpdatePageContentCommand $command
     * @return void
     * @throws PageNotFoundException|AuthorizationException
     */
    public function handle(UpdatePageContentCommand $command): void
    {
        /** @var Page|null $page */
        $page = Page::find($command->pageId);

        if (!$page) {
            throw new PageNotFoundException("Page with ID {$command->pageId} not found.");
        }

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // Kiểm tra quyền: người dùng có được phép cập nhật trang này không?
        $user->can('update', $page);

        $page->content = $command->blocks;
        $page->featured_image_path = $command->featuredImagePath;

        $page->save();
    }
}
