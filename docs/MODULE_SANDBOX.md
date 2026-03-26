# Module sandbox & permissions

When the sandbox is enabled, code that runs **in a module context** (e.g. extension point handlers) can only use resources that the module declared in its `module.json` **permissions** array. This reduces risk when installing plugins from a marketplace or untrusted source.

## Enabling the sandbox

In `.env`:

```env
MODULE_SANDBOX_ENABLED=true
```

Or in config:

- `config/module_sandbox.php`: `'enabled' => true`, `'enforce_cache' => true`.

When disabled (default), no permission checks are applied and all modules behave as before.

## How it works

1. **Module context**  
   When an extension point handler is invoked, the registry sets the current “module” to the one that registered that handler (from `extensions.php` or the loader). All code run inside that handler (including `cache()`, etc.) is considered to run in that module’s context.

2. **Permissions in manifest**  
   Each module declares what it needs in `module.json`:

   ```json
   "permissions": [
     "cache:read",
     "cache:write",
     "storage:read",
     "storage:write",
     "events:subscribe",
     "events:publish",
     "database:read",
     "database:write",
     "network:out"
   ]
   ```

3. **Enforcement**  
   When the sandbox is enabled and `enforce_cache` is true, the default cache store is wrapped. Any cache read (e.g. `cache()->get()`) requires `cache:read`; any write (e.g. `cache()->set()`, `cache()->delete()`) requires `cache:write`. If the current module does not have the permission, a `ModulePermissionDeniedException` is thrown.

4. **No context**  
   When no module is in context (e.g. core, CLI, or normal request handling outside an extension handler), the gate allows all operations so existing behaviour is unchanged.

## Permission tokens

| Token             | Meaning (current or planned)     |
|-------------------|-----------------------------------|
| `cache:read`      | Allowed to read from cache        |
| `cache:write`     | Allowed to write/delete cache     |
| `storage:read`    | Reserved (future filesystem read) |
| `storage:write`   | Reserved (future filesystem write)|
| `database:read`   | Reserved (future DB select)       |
| `database:write`  | Reserved (future DB insert/update/delete) |
| `events:subscribe`| Reserved (listen to events)       |
| `events:publish`  | Reserved (dispatch events)        |
| `network:out`     | Reserved (outgoing HTTP)          |

Only **cache** is enforced today; the rest are for documentation and future use.

## Running code in a module context

If you need to run a callable “as” a module (e.g. in a job or CLI):

```php
use Core\Module\Sandbox\ModuleContext;

ModuleContext::runInModule('User', function () {
    // cache()->get/set here will be checked against User's permissions
    cache()->set('user:temp', $data);
});
```

## Errors

If a module uses cache without the right permission you’ll see:

`Module 'X' is not allowed to perform 'cache:write'. Add it to the module's permissions in module.json.`

Add the required permission to that module’s `permissions` array in `module.json` and redeploy.
