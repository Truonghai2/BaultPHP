<?php

namespace Core\CQRS;

use Core\Support\Result;

/**
 * Command Handler interface.
 * 
 * Command handlers execute commands and modify application state.
 * They should be:
 * - Transactional
 * - Idempotent when possible
 * - Return Result<void>
 * 
 * Example:
 * class AddTodoCommandHandler implements CommandHandler {
 *     public function handle(Command $command): Result {
 *         // 1. Validate
 *         // 2. Create entity
 *         // 3. Save to repository
 *         // 4. Publish events
 *         return Result::ok();
 *     }
 * }
 */
interface CommandHandler
{
    /**
     * Execute the command.
     * 
     * @param Command $command
     * @return Result<void>
     */
    public function handle(Command $command): Result;

    /**
     * Get the command class this handler handles.
     *
     * @return string
     */
    public function getCommandClass(): string;

    /**
     * Get the bounded context.
     *
     * @return string
     */
    public function getBoundedContext(): string;
}
