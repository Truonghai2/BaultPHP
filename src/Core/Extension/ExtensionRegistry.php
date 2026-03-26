<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Module\Sandbox\ModuleContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The central Extension Point Registry.
 *
 * Core and modules interact with it through three primitives:
 *
 *   FILTER    apply_filter('block.render', $html, $ctx)
 *   ACTION    run_action('module.booted', $ctx)
 *   COLLECTOR collect_extensions('view.global_data', $ctx)
 *
 * Extension points must be declared before handlers are registered,
 * so the registry can validate the type contract at dev time.
 *
 * Thread-safety note: In Swoole the registry is populated once at boot
 * inside a worker process (register → read-only during requests). No locks
 * are needed.
 */
final class ExtensionRegistry
{
    /** @var array<string, ExtensionPointType> declared extension points */
    private array $declared = [];

    /** @var array<string, list<RegisteredHandler>> handlers keyed by point name */
    private array $handlers = [];

    private bool $strict;

    public function __construct(
        private readonly LoggerInterface $logger,
        /** When true, using an undeclared point throws an exception. */
        bool $strict = false,
    ) {
        $this->strict = $strict;
    }

    // =========================================================================
    // Declaration
    // =========================================================================

    /**
     * Declare a named extension point.
     * Should be called from service providers, before any handler registrations.
     */
    public function declare(
        string $name,
        ExtensionPointType $type,
        string $description = '',
    ): void {
        if (isset($this->declared[$name])) {
            // Allow re-declaration with same type (idempotent)
            if ($this->declared[$name] === $type) {
                return;
            }
            throw new \LogicException(
                "Extension point '{$name}' already declared with type '{$this->declared[$name]->value}'; cannot redeclare as '{$type->value}'.",
            );
        }
        $this->declared[$name] = $type;
        $this->handlers[$name] = [];
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Register a handler for a named extension point.
     *
     * @param callable $handler   Signature depends on the point type:
     *                            FILTER:    fn(mixed $value, array $ctx): mixed
     *                            ACTION:    fn(array $ctx): void
     *                            COLLECTOR: fn(array $ctx): array
     * @param int      $priority  Lower runs first (0 = earliest, 100 = latest).
     * @param string|null $registeredBy Optional FQCN of registering class.
     */
    public function register(
        string $name,
        callable $handler,
        int $priority = 10,
        ?string $registeredBy = null,
    ): void {
        $this->ensureExists($name);
        $this->handlers[$name][] = new RegisteredHandler($name, $handler, $priority, $registeredBy);
        // Keep sorted by priority (ascending)
        usort($this->handlers[$name], fn (RegisteredHandler $a, RegisteredHandler $b) => $a->priority <=> $b->priority);
    }

    // =========================================================================
    // Execution
    // =========================================================================

    /**
     * FILTER — pass $value through all registered handlers.
     * Each handler receives the (possibly mutated) value from the previous one.
     *
     * @param mixed $value   The initial value.
     * @param array $context Additional context data (read-only for handlers).
     * @return mixed         The final filtered value.
     */
    public function filter(string $name, mixed $value, array $context = []): mixed
    {
        $this->assertType($name, ExtensionPointType::FILTER);

        foreach ($this->handlers[$name] ?? [] as $entry) {
            try {
                $value = ModuleContext::runInModule($entry->registeredBy, fn () => ($entry->handler)($value, $context));
            } catch (Throwable $e) {
                $this->logger->error(
                    "Extension filter '{$name}' handler failed: {$e->getMessage()}",
                    ['registered_by' => $entry->registeredBy, 'exception' => get_class($e)],
                );
            }
        }

        return $value;
    }

    /**
     * ACTION — call all registered handlers for side-effects.
     * Failures are logged but do not stop subsequent handlers.
     *
     * @param array $context Context data passed to every handler.
     */
    public function action(string $name, array $context = []): void
    {
        $this->assertType($name, ExtensionPointType::ACTION);

        foreach ($this->handlers[$name] ?? [] as $entry) {
            try {
                ModuleContext::runInModule($entry->registeredBy, fn () => ($entry->handler)($context));
            } catch (Throwable $e) {
                $this->logger->error(
                    "Extension action '{$name}' handler failed: {$e->getMessage()}",
                    ['registered_by' => $entry->registeredBy, 'exception' => get_class($e)],
                );
            }
        }
    }

    /**
     * COLLECTOR — call each handler and merge all returned arrays.
     * Handlers returning non-arrays are skipped with a warning.
     *
     * @param array $context Context data passed to every handler.
     * @return array         Merged result of all handlers.
     */
    public function collect(string $name, array $context = []): array
    {
        $this->assertType($name, ExtensionPointType::COLLECTOR);

        $result = [];
        foreach ($this->handlers[$name] ?? [] as $entry) {
            try {
                $contribution = ModuleContext::runInModule($entry->registeredBy, fn () => ($entry->handler)($context));
                if (!is_array($contribution)) {
                    $this->logger->warning(
                        "Extension collector '{$name}' handler must return array; got " . gettype($contribution),
                        ['registered_by' => $entry->registeredBy],
                    );
                    continue;
                }
                $result = array_merge_recursive($result, $contribution);
            } catch (Throwable $e) {
                $this->logger->error(
                    "Extension collector '{$name}' handler failed: {$e->getMessage()}",
                    ['registered_by' => $entry->registeredBy, 'exception' => get_class($e)],
                );
            }
        }

        return $result;
    }

    // =========================================================================
    // Introspection
    // =========================================================================

    public function isDeclared(string $name): bool
    {
        return isset($this->declared[$name]);
    }

    public function typeOf(string $name): ?ExtensionPointType
    {
        return $this->declared[$name] ?? null;
    }

    /** @return array<string, ExtensionPointType> */
    public function allDeclared(): array
    {
        return $this->declared;
    }

    /** @return list<RegisteredHandler> */
    public function handlersFor(string $name): array
    {
        return $this->handlers[$name] ?? [];
    }

    /** @return array<string, list<RegisteredHandler>> */
    public function allHandlers(): array
    {
        return $this->handlers;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function ensureExists(string $name): void
    {
        if (!isset($this->declared[$name])) {
            if ($this->strict) {
                throw new \InvalidArgumentException(
                    "Cannot register handler for undeclared extension point '{$name}'. "
                    . "Declare it first with ExtensionRegistry::declare().",
                );
            }
            // Auto-declare as ACTION in permissive mode (developer convenience)
            $this->logger->debug("Auto-declaring extension point '{$name}' as ACTION (not explicitly declared).");
            $this->declared[$name] = ExtensionPointType::ACTION;
            $this->handlers[$name] = [];
        }
    }

    private function assertType(string $name, ExtensionPointType $expected): void
    {
        $actual = $this->declared[$name] ?? null;

        if ($actual === null) {
            // Point not declared — allow in permissive mode, just no handlers
            return;
        }

        if ($actual !== $expected) {
            throw new \LogicException(
                "Extension point '{$name}' is of type '{$actual->value}'; cannot call as '{$expected->value}'.",
            );
        }
    }
}
