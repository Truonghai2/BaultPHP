<?php

declare(strict_types=1);

namespace Core\Extension;

/**
 * Canonical names for all extension points declared by BaultFrame core.
 *
 * Convention: "<subsystem>.<action_or_noun>"
 *
 * ───────────────────────────────────────────────────────────────────
 * Usage in a module's extensions.php:
 *
 *   use Core\Extension\CoreExtensionPoints as EP;
 *
 *   return [
 *       EP::VIEW_GLOBAL_DATA => [MyModule\Extensions\ViewDataProvider::class, 'provide'],
 *       EP::BLOCK_RENDER     => [MyModule\Extensions\BlockDecorator::class, 'decorate'],
 *   ];
 * ───────────────────────────────────────────────────────────────────
 */
final class CoreExtensionPoints
{
    // ─── View ────────────────────────────────────────────────────────────────

    /**
     * COLLECTOR — contribute variables that will be available in every rendered view.
     *
     * Handler signature: fn(array $ctx): array<string, mixed>
     *
     * Example: return ['currentUser' => auth()->user(), 'appVersion' => '1.0'];
     */
    public const VIEW_GLOBAL_DATA = 'view.global_data';

    /**
     * FILTER — transform the rendered HTML of a view just before it is returned.
     *
     * Handler signature: fn(string $html, array $ctx): string
     *
     * Context keys: view (string), data (array)
     */
    public const VIEW_RENDER = 'view.render';

    // ─── Block (CMS) ─────────────────────────────────────────────────────────

    /**
     * FILTER — transform a block's rendered HTML before it is inserted into the page.
     *
     * Handler signature: fn(string $html, array $ctx): string
     *
     * Context keys: block_type (string), block_id (int|null), config (array)
     */
    public const BLOCK_RENDER = 'block.render';

    /**
     * COLLECTOR — add new block types visible to the CMS block picker.
     *
     * Handler signature: fn(array $ctx): array<string, array{label:string, icon?:string}>
     *
     * Example: return ['my_widget' => ['label' => 'My Widget', 'icon' => 'star']];
     */
    public const BLOCK_TYPES = 'block.types';

    // ─── HTTP ────────────────────────────────────────────────────────────────

    /**
     * COLLECTOR — add middleware classes to the global HTTP stack.
     * Handlers run during provider boot, before the first request.
     *
     * Handler signature: fn(array $ctx): list<class-string<MiddlewareInterface>>
     */
    public const HTTP_GLOBAL_MIDDLEWARE = 'http.global_middleware';

    /**
     * COLLECTOR — register named (route) middleware aliases.
     *
     * Handler signature: fn(array $ctx): array<string, class-string>
     *
     * Example: return ['my.auth' => MyAuthMiddleware::class];
     */
    public const HTTP_ROUTE_MIDDLEWARE = 'http.route_middleware';

    /**
     * FILTER — transform the PSR-7 Response just before it is sent to the client.
     *
     * Handler signature: fn(ResponseInterface $response, array $ctx): ResponseInterface
     *
     * Context keys: request (ServerRequestInterface)
     */
    public const RESPONSE_BEFORE_SEND = 'response.before_send';

    // ─── ACL / Auth ──────────────────────────────────────────────────────────

    /**
     * FILTER — override or augment the result of a permission check.
     * Return true to explicitly GRANT, false to explicitly DENY,
     * or the incoming $allowed value to leave the decision unchanged.
     *
     * Handler signature: fn(bool $allowed, array $ctx): bool
     *
     * Context keys: user_id (int), permission (string), subject (mixed)
     */
    public const ACL_CHECK = 'acl.check';

    /**
     * ACTION — fired after a user authenticates successfully.
     *
     * Handler signature: fn(array $ctx): void
     *
     * Context keys: user_id (int), guard (string), remember (bool)
     */
    public const AUTH_AUTHENTICATED = 'auth.authenticated';

    // ─── Navigation ──────────────────────────────────────────────────────────

    /**
     * COLLECTOR — contribute items to the admin navigation sidebar.
     *
     * Handler signature: fn(array $ctx): array<int, array{label:string, url:string, icon?:string, order?:int, children?:array}>
     */
    public const NAVIGATION_ADMIN = 'navigation.admin';

    /**
     * COLLECTOR — contribute items to the front-end navigation menu.
     *
     * Handler signature: fn(array $ctx): array<int, array{label:string, url:string, order?:int, children?:array}>
     */
    public const NAVIGATION_FRONTEND = 'navigation.frontend';

    // ─── Scheduling ──────────────────────────────────────────────────────────

    /**
     * ACTION — called when the scheduler starts; register recurring tasks here.
     *
     * Handler signature: fn(array $ctx): void
     *
     * Context keys: scheduler (SchedulerInterface)
     */
    public const SCHEDULE_TASKS = 'schedule.tasks';

    // ─── Module lifecycle (complement to ModuleLifecycleDispatcher) ───────────

    /**
     * ACTION — fired after any module finishes booting.
     *
     * Handler signature: fn(array $ctx): void
     *
     * Context keys: module (string), manifest (ModuleManifest)
     */
    public const MODULE_BOOTED = 'module.booted';

    /**
     * FILTER — allow external code to alter CLI commands registered by a module.
     *
     * Handler signature: fn(array $commands, array $ctx): array<class-string>
     *
     * Context keys: module (string)
     */
    public const MODULE_COMMANDS = 'module.commands';

    // ─── Queue ───────────────────────────────────────────────────────────────

    /**
     * ACTION — called when a job fails after all retry attempts.
     *
     * Handler signature: fn(array $ctx): void
     *
     * Context keys: job (BaseJob), exception (Throwable), connection (string)
     */
    public const QUEUE_JOB_FAILED = 'queue.job_failed';

    // prevent instantiation
    private function __construct() {}
}
