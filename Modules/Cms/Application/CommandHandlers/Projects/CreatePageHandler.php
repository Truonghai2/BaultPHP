<?php

declare(strict_types=1);

namespace Modules\Cms\Application\CommandHandlers\Projects;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\EventSourcing\Contracts\EventDispatcherInterface;
use Modules\Cms\Application\Commands\Page\CreatePageCommand;
use Modules\Cms\Domain\Repositories\PageRepositoryInterface;
use Modules\Cms\Domain\Services\PageDomainService;
use Modules\Cms\Infrastructure\Models\PageListItem;

/**
 * Use Case (Command Handler) for creating a new page.
 *
 * This class orchestrates the creation process:
 * 1. Receives a CreatePageCommand.
 * 2. Uses PageDomainService to validate business rules.
 * 3. Calls the static factory method on PageAggregate to create a new instance.
 * 4. Persists the new aggregate using the PageRepository.
 * 5. Dispatches the recorded domain events.
 */
class CreatePageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageDomainService $domainService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Handles the CreatePageCommand.
     *
     * @param CreatePageCommand $command
     * @return mixed
     */
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof CreatePageCommand) {
            throw new \InvalidArgumentException('Command must be an instance of CreatePageCommand.');
        }

        $this->domainService->validatePageName($command->name);
        $this->domainService->validateSlug($command->slug);

        $page = PageListItem::create([
            'name' => $command->name,
            'slug' => $command->slug,
            'author_id' => $command->userId,
            'status' => $command->status,
            'meta_title' => $command->metaTitle ?? $command->name,
            'meta_description' => $command->metaDescription,
        ]);

        return $page->id;
    }
}