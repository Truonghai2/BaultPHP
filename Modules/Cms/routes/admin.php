<?php

use Core\Routing\Router;
use Modules\Cms\Http\Controllers\PageBlockController;
use Modules\Cms\Http\Controllers\PageController;
use Modules\Cms\Infrastructure\Models\PageBlock;

return function (Router $router) {
    $router->get('/pages/{id}/editor', [PageController::class, 'editor'])->name('admin.pages.editor');

    // Định nghĩa route để xóa một block.
    // ->middleware('permission:delete,block'):
    //   - 'delete': Tên quyền, sẽ được map tới phương thức `delete()` trong Policy.
    //   - 'block': Tên tham số trong URL, để middleware biết cần tìm model nào.
    $router->delete('/blocks/{block}', [PageBlockController::class, 'destroy'])
           ->name('admin.blocks.destroy')
           ->middleware(['permission:delete,block'])
           ->model('block', PageBlock::class);
};
