<?php

namespace Modules\Cms\Http\Controllers;

use Core\CQRS\Command\DispatchesCommands;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\Cms\Application\Commands\DeleteBlockCommand;
use Modules\Cms\Infrastructure\Models\PageBlock;

#[Route(prefix: '/admin', middleware: ['auth'], group: 'web')]
class PageBlockController extends Controller
{
    use DispatchesCommands;

    /**
     * Handle the request to delete a page block.
     * Authorization is handled by the 'permission' middleware and re-verified in the command handler.
     *
     * Note: This uses old PageBlock system. For new block system, use BlockManagementController
     */
    #[Route('/page-blocks/{block}', method: 'DELETE', name: 'admin.page-blocks.destroy', middleware: ['permission:delete,block'])]
    public function destroy(PageBlock $block)
    {
        $this->dispatch(new DeleteBlockCommand($block->id));

        return response()->json(['message' => 'Block deleted successfully.']);
    }
}
