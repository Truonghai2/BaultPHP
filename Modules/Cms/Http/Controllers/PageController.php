<?php

namespace Modules\Cms\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Modules\Cms\Application\Queries\PageFinder;
use Modules\Cms\Http\Components\PageEditor;
use Psr\Http\Message\ResponseInterface as Response;

#[Route(prefix: '/admin/pages', middleware: ['auth'], group: 'web')]
class PageController extends Controller
{
    public function __construct(
        private readonly PageFinder $pageFinder,
    ) {
    }

    /**
     * Display the page editor component.
     *
     * This method is responsible for authorizing the user and rendering the
     * live-wire style component that allows for page editing.
     *
     * @param int $id The ID of the page to edit.
     * @return Response
     * @throws \App\Exceptions\AuthorizationException Thrown by the policy if access is denied.
     * @throws \Modules\Cms\Domain\Exceptions\PageNotFoundException
     */
    #[Route('/{id}/editor', method: 'GET', name: 'admin.pages.editor')]
    public function editor(int $id): Response
    {
        $page = $this->pageFinder->findById($id);

        /** @var \Modules\User\Infrastructure\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            // This case should ideally be handled by an authentication middleware.
            return response('Unauthorized', 401);
        }

        // Check if the user is authorized to update this page. The `can` method
        // will delegate to the AccessControlService, which finds the PagePolicy
        // and executes its `update` method. An exception is thrown on failure.
        $user->can('update', $page);

        // The PageEditor component handles its own data fetching and rendering.
        // We instantiate it via the service container to resolve its dependencies.
        $editorComponent = app(PageEditor::class);
        $editorComponent->mount(['page' => $page]);
        $html = $editorComponent->render();

        return response($html);
    }
}
