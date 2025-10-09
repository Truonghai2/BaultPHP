<?php

namespace Core\CQRS\Command;

/**
 * A marker interface for commands that should be wrapped in a database transaction.
 *
 * Any command implementing this interface will automatically have its handler's execution
 * managed within a transaction by the DatabaseTransactionMiddleware.
 */
interface TransactionalCommand extends Command
{
}
