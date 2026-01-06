<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use App\Exceptions\AuthorizationException;
use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Modules\Cms\Domain\Exceptions\PageNotFoundException;
use Modules\Cms\Domain\Repositories\PageRepositoryInterface;
use Modules\Cms\Domain\Services\PageService;
use Modules\Cms\Domain\ValueObjects\PageContent;
use Modules\Cms\Domain\ValueObjects\PageId;
use Modules\User\Domain\Services\AuthorizationService;

/**
 * Update Page Content Command Handler
 *
 * CQRS Command Handler following DDD standards:
 * - Does not access Infrastructure directly
 * - Uses Repository pattern
 * - Delegates business logic to Domain Service
 */
class UpdatePageContentCommandHandler implements CommandHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageService $pageService,
        private readonly AuthorizationService $authorizationService,
    ) {
    }

    /**
     * @param Command|UpdatePageContentCommand $command
     * @throws PageNotFoundException
     * @throws AuthorizationException
     */
    public function handle(Command $command): void
    {
        /** @var UpdatePageContentCommand $command */

        // 1. Load page from repository
        $pageId = new PageId($command->pageId);
        $page = $this->pageRepository->findById($pageId);

        // 2. Check authorization (if userId is provided)
        if ($command->userId) {
            $this->authorizationService->ensureCanUpdate($command->userId, $page);
        }

        // 3. Update page content using domain service
        $content = PageContent::fromArray(['blocks' => $command->blocks]);
        $updatedPage = $this->pageService->updateContent(
            $page,
            $content,
            $command->featuredImagePath,
        );

        // 4. Persist changes
        $this->pageRepository->save($updatedPage);
    }
}
