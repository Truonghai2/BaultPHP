<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Modules\Cms\Infrastructure\Models\Page;

class AddBlockToPageCommand implements Command
{
    /**
     * @param Page $page Trang mà block sẽ được thêm vào.
     * @param string $componentClass Class của component block.
     */
    public function __construct(
        public readonly Page $page,
        public readonly string $componentClass,
    ) {
    }
}
