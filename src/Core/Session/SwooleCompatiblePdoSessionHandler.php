<?php

namespace Core\Session;

use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * A Swoole-compatible PDO session handler.
 *
 * This class extends Symfony's PdoSessionHandler to make it work safely in a
 * Swoole coroutine environment. Instead of holding a persistent PDO connection,
 * it dynamically acquires and releases a connection from a SwoolePdoPool for
 * each operation.
 *
 * This approach prevents connection conflicts between concurrent requests and
 * leverages the battle-tested logic of the original PdoSessionHandler, including
 * its robust locking mechanisms.
 */
class SwooleCompatiblePdoSessionHandler extends PdoSessionHandler
{
    /**
     * @param string $connectionName The name of the PDO pool connection.
     * @param array $options Session options, same as PdoSessionHandler.
     */
    public function __construct(
        private string $connectionName,
        #[\SensitiveParameter] private array $options = [],
    ) {
        // We don't call the parent constructor immediately because we don't have a PDO
        // instance yet. We will manage the connection ourselves.
        // The 'lazy' connection concept of the parent is not used here.
    }

    /**
     * Executes a callback with a temporary PDO connection from the pool.
     *
     * @param \Closure $callback The operation to perform.
     * @return mixed The result of the callback.
     */
    private function withConnection(\Closure $callback, mixed $failureReturnValue = false): mixed
    {
        $pdo = null;
        try {
            $pdo = SwoolePdoPool::get($this->connectionName);

            if (! $pdo instanceof PDO) {
                return $failureReturnValue;
            }

            // Temporarily assign the PDO instance to the private parent property
            // using Reflection so the parent's methods can use it.
            $this->setPrivateParentProperty('pdo', $pdo);
            $this->setPrivateParentProperty('driver', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

            return $callback($this);
        } finally {
            if ($pdo instanceof PDO) {
                SwoolePdoPool::put($pdo, $this->connectionName);
            }

            $this->unsetPrivateParentProperty('pdo');
            $this->unsetPrivateParentProperty('driver');
        }
    }

    /**
     * A helper to set private properties on the parent PdoSessionHandler class.
     */
    private function setPrivateParentProperty(string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty(PdoSessionHandler::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($this, $value);
    }

    /**
     * A helper to unset private properties on the parent PdoSessionHandler class.
     */
    private function unsetPrivateParentProperty(string $property): void
    {
        $unsetter = function () use ($property) {
            unset($this->{$property});
        };
        $unsetter->call($this);
    }

    /**
     * Override parent methods to wrap them in our connection management logic.
     */
    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function read(#[\SensitiveParameter] string $sessionId): string
    {
        return $this->withConnection(fn (parent $handler) => $handler->read($sessionId), '');
    }

    public function write(#[\SensitiveParameter] string $sessionId, string $data): bool
    {
        return $this->withConnection(fn (parent $handler) => $handler->write($sessionId, $data));
    }

    public function destroy(#[\SensitiveParameter] string $sessionId): bool
    {
        return $this->withConnection(fn (parent $handler) => $handler->destroy($sessionId));
    }

    public function gc(int $maxlifetime): int|false
    {
        return $this->withConnection(fn (parent $handler) => $handler->gc($maxlifetime));
    }

    public function close(): bool
    {
        // The connection is released in withConnection, so this is a no-op.
        return true;
    }

    public function updateTimestamp(#[\SensitiveParameter] string $sessionId, string $data): bool
    {
        return $this->withConnection(fn (parent $handler) => $handler->updateTimestamp($sessionId, $data));
    }
}
