# Declarative module config

Modules can declare **routes**, **permissions**, and **menu** in a manifest file instead of (or in addition to) PHP. Core loads these once and registers routes and navigation without extra boilerplate.

## Manifest file

Place one of these in your module directory:

- `Modules/<Name>/manifest.yaml`
- `Modules/<Name>/manifest.json`

Example **manifest.yaml**:

```yaml
# Routes: method, uri, action (Controller@method), optional name, middleware, group
routes:
  - method: GET
    uri: /profile
    action: ProfileController@show
    name: user.profile
    middleware: [auth]
    group: web
  - method: GET
    uri: /admin/users
    action: UserController@index
    name: admin.users.index
    middleware: [auth, can:manage-users]
    group: web

# Permissions this module introduces (for ACL discovery)
permissions:
  - manage-users
  - manage-roles
  - view-users

# Admin sidebar (merged with extension point navigation.admin)
menu:
  admin:
    - label: Users
      url: /admin/users
      icon: users
      order: 20
      children:
        - label: All Users
          url: /admin/users
        - label: Roles
          url: /admin/roles

# Frontend nav (merged with navigation.frontend)
menu:
  frontend:
    - label: Profile
      url: /profile
      order: 10
```

## Action format

- **Short:** `ProfileController@show` → resolved to `Modules\<Name>\Http\Controllers\ProfileController::show`
- **FQCN:** `Modules\User\Http\Controllers\ProfileController@show` → used as-is

## Menu

Menu from `menu.admin` and `menu.frontend` is automatically contributed to the **navigation.admin** and **navigation.frontend** extension points (priority 5). It is merged with items from module `extensions.php` and other collectors. If you define menu in both `manifest.yaml` and `extensions.php`, you may get duplicate items—prefer one or the other, or use manifest for structure and extensions for permission-gated items.

## Permissions

`permissions` is a list of permission keys. Use it for documentation and for ACL discovery; actual permission checks still use your ACL layer (e.g. role/permission tables). Optional.

## Route cache

Declarative routes are included when you run `php cli route:cache` (they are registered during `mapRoutes()` before the cache is built). No extra step required.

## JSON example

**manifest.json** equivalent (same structure):

```json
{
  "routes": [
    {
      "method": "GET",
      "uri": "/profile",
      "action": "ProfileController@show",
      "name": "user.profile",
      "middleware": ["auth"],
      "group": "web"
    }
  ],
  "permissions": ["manage-users", "view-users"],
  "menu": {
    "admin": [
      {
        "label": "Users",
        "url": "/admin/users",
        "icon": "users",
        "order": 20
      }
    ]
  }
}
```
