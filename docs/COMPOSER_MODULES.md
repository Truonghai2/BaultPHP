# Composer package-based modules

Modules can be installed as Composer packages instead of living under `Modules/`. Use this for versioning, dependencies, and reuse across projects.

## Package format

### 1. Package type

In your package `composer.json`:

```json
{
  "name": "acme/bault-module-blog",
  "type": "bault-module",
  "description": "Blog module for BaultFrame",
  "autoload": {
    "psr-4": {
      "Acme\\Blog\\": "src/"
    }
  },
  "extra": {
    "bault": {
      "module": {
        "name": "Blog",
        "providers": [
          "Acme\\Blog\\Providers\\BlogServiceProvider"
        ],
        "description": "Optional short description",
        "alias": "blog",
        "api_version": 2,
        "min_core_version": "1.0.0",
        "capabilities": ["blog", "posts"],
        "permissions": ["cache:read", "cache:write", "database:read", "database:write"],
        "activate": "on_boot",
        "route_prefix": "/blog"
      }
    }
  }
}
```

- **`type": "bault-module"`** — identifies the package as a Bault module (optional if `extra.bault.module` is present).
- **`extra.bault.module`** — required. Must contain at least **`name`** and **`providers`** (array of service provider FQCNs).

Other keys under `extra.bault.module` mirror `module.json`: `description`, `alias`, `api_version`, `min_core_version`, `capabilities`, `permissions`, `hooks`, `activate`, `route_prefix`, `route_prefixes`, `require`, etc.

### 2. Install in the app

In the application (BaultFrame project):

```bash
composer require acme/bault-module-blog
```

After `composer install` or `composer update`, the framework discovers the package under `vendor/acme/bault-module-blog`, reads `extra.bault.module`, and treats it as an enabled module: providers are merged into boot/lazy discovery, and paths (events, extensions, declarative manifest) are resolved to the package root.

### 3. Disable a composer module

By default every discovered composer module is **enabled**. To disable one:

- **Config:** in `config/modules.php` set `composer_disabled` to a list of package names:

  ```php
  'composer_disabled' => ['acme/bault-module-blog'],
  ```

- **Env:** set `MODULES_COMPOSER_DISABLED=acme/bault-module-blog` (comma-separated for multiple).

### 4. Path resolution

- **Events:** `events.php` is loaded from the package root (same as `Modules/Name/events.php`).
- **Extensions:** `extensions.php` is loaded from the package root.
- **Declarative:** `manifest.yaml` / `manifest.json` are read from the package root.

Core uses `ModulePathResolver` so that both `Modules/Name` and `vendor/vendor/package` are supported.

### 5. Caching

Run `php cli bootstrap:cache` (or `optimize`) so that the enabled module list (including composer modules) is written to `bootstrap/cache/modules.php`. Otherwise discovery runs on each request.

## Example minimal package

```json
{
  "name": "my/bault-module-hello",
  "type": "bault-module",
  "version": "1.0.0",
  "autoload": {
    "psr-4": {
      "My\\Hello\\": "src/"
    }
  },
  "extra": {
    "bault": {
      "module": {
        "name": "Hello",
        "providers": [
          "My\\Hello\\HelloServiceProvider"
        ]
      }
    }
  }
}
```

If `extra.bault.module` is omitted but `type` is `bault-module`, the module name is inferred from the package name (e.g. `bault-module-hello` → `Hello`).
