<?php

namespace Modules\Cms\Http\Controllers;

use Core\CQRS\Command\DispatchesCommands;
use Modules\Cms\Application\Commands\DeleteBlockCommand;
use Modules\Cms\Infrastructure\Models\PageBlock;

class PageBlockController
{
    use DispatchesCommands;

    /**
     * Handle the request to delete a page block.
     * Authorization is handled by the 'permission' middleware and re-verified in the command handler.
     */
    public function destroy(PageBlock $block)
    {
        $this->dispatch(new DeleteBlockCommand($block->id));

        return response()->json(['message' => 'Block deleted successfully.']);
    }
}
