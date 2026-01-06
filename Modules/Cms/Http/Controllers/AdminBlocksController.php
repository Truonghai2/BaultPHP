<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Admin Blocks View Controller
 */
#[Route(prefix: '/admin', middleware: ['auth'], group: 'web')]
class AdminBlocksController extends Controller
{
    /**
     * Show block manager interface
     */
    #[Route('/cms/blocks', method: 'GET', name: 'admin.cms.blocks')]
    public function index(Request $request): Response
    {
        return response(view('cms.admin.blocks.index')->render());
    }

    /**
     * Show visual block editor (Moodle-like)
     */
    #[Route('/cms/blocks/visual', method: 'GET', name: 'admin.cms.blocks.visual')]
    public function visual(Request $request): Response
    {
        return response(view('cms.admin.blocks.visual-editor')->render());
    }

    /**
     * Show page editor interface
     */
    #[Route('/cms/pages/{id}/edit', method: 'GET', name: 'admin.cms.pages.edit')]
    public function editPage(int $id): Response
    {
        return response(view('cms.admin.pages.edit', ['pageId' => $id])->render());
    }
}

