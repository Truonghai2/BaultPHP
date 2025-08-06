<?php

namespace Modules\Cms\Application\Queries;

use Modules\Cms\Domain\Exceptions\PageNotFoundException;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * Defines the contract for finding Page models.
 * This interface allows for different implementations, such as a direct database finder
 * or a cached finder, to be used interchangeably.
 */
interface PageFinderInterface
{
    /**
     * @throws PageNotFoundException
     */
    public function findById(int $id): Page;
}
