# 🔐 Login Redirect System - Technical Documentation

## Overview

Hệ thống redirect sau login đã được cải tiến để:

- ✅ Phân biệt user thường và admin
- ✅ Không lưu admin URLs vào intended redirect
- ✅ Smart routing dựa trên user roles
- ✅ Graceful fallback nếu routes không tồn tại

---

## Architecture

### 1. **Authentication Middleware** (`src/Http/Middleware/Authenticate.php`)

**Responsibility:** Bảo vệ protected routes và lưu intended URL cho redirect sau login.

**Key Logic:**

```php
// Only save intended URL for NON-ADMIN routes
$path = $request->getUri()->getPath();
if (!str_starts_with($path, '/admin')) {
    $this->session->set('url.intended', (string) $request->getUri());
}
```

**Why?**

- Admin URLs (e.g., `/admin/blocks/types`) không nên được lưu làm intended
- User thường cố access `/about` → Lưu và redirect về sau login ✅
- User cố access `/admin/...` → KHÔNG lưu, redirect về default ✅

**Security Benefits:**

- ✅ Không leak admin routes cho unauthenticated users
- ✅ Admin luôn được redirect đến admin dashboard, không phải random admin URL
- ✅ Tránh confusion khi user login và bị redirect đến trang admin không mong muốn

---

### 2. **Login Controller** (`Modules/User/Http/Controllers/Auth/LoginController.php`)

**Responsibility:** Xử lý authentication và smart redirect dựa trên user role.

**Key Logic:**

```php
// Determine redirect destination based on user roles
$defaultRoute = 'home';

// If user is admin, redirect to admin dashboard (if exists)
if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
    // Try admin dashboard route, fallback to admin pages
    if (route_exists('admin.dashboard')) {
        $defaultRoute = 'admin.dashboard';
    } elseif (route_exists('admin.pages.index')) {
        $defaultRoute = 'admin.pages.index';
    }
}

return redirect()->intended(route($defaultRoute));
```

**Decision Flow:**

```
User login success
    ↓
Check user roles
    ↓
    ├─ Has 'admin' or 'super_admin'?
    │   ↓
    │   ├─ Route 'admin.dashboard' exists? → Use it
    │   ├─ Route 'admin.pages.index' exists? → Use it
    │   └─ Fallback → 'home'
    │
    └─ Regular user → 'home'
    ↓
Apply redirect()->intended($defaultRoute)
    ↓
    ├─ Session has 'url.intended'? → Redirect there
    └─ No intended URL? → Redirect to $defaultRoute
```

**Role Checking:**

- `$user->hasRole('admin')` - Checks role in system context (ID = 1)
- `$user->hasRole('super_admin')` - Checks super admin role
- Context-aware: Supports hierarchical context checking (via AccessControlService)

---

### 3. **Helper Function** (`src/Core/helpers.php`)

**New Function:** `route_exists(string $name): bool`

**Implementation:**

```php
function route_exists(string $name): bool
{
    try {
        app(UrlGenerator::class)->route($name);
        return true;
    } catch (\Throwable) {
        return false;
    }
}
```

**Purpose:**

- Safe check nếu route tồn tại trước khi redirect
- Tránh `RouteNotFoundException` khi route chưa được define
- Graceful fallback nếu admin routes chưa setup

**Usage Examples:**

```php
// Check before redirect
if (route_exists('admin.dashboard')) {
    $url = route('admin.dashboard');
}

// Conditional routing
$destination = route_exists('custom.home')
    ? route('custom.home')
    : route('home');
```

---

## Integration with AccessControlService

### How `hasRole()` Works

**User Model Method:**

```php
public function hasRole(string $roleName, $context = null): bool
{
    return app(AccessControlService::class)->hasRole($this, $roleName, $context);
}
```

**AccessControlService Logic:**

```php
public function hasRole(User $user, string $roleName, $context = null): bool
{
    // Super-admin has all roles
    if ($this->isSuperAdmin($user)) {
        return true;
    }

    // Load user permissions cache
    if (!isset($this->permissionCache[$user->id])) {
        $this->loadAndCacheUserPermissions($user);
    }

    // Resolve context (null → system context)
    $context = $this->resolveContext($context);
    $contextIds = $this->getContextHierarchyIds($context);

    // Check role in context hierarchy
    $userContexts = $this->permissionCache[$user->id]['contexts'] ?? [];

    foreach ($contextIds as $contextId) {
        $rolesInContext = $userContexts[$contextId]['roles'] ?? [];
        if (in_array($roleName, $rolesInContext, true)) {
            return true;
        }
    }

    return false;
}
```

**Context Resolution:**

```php
public function resolveContext(mixed $context): Context
{
    if ($context === null) {
        // Default to system context (ID = 1)
        if ($this->systemContext === null) {
            $systemContext = Context::findOrFail(self::SYSTEM_CONTEXT_ID);
            $this->systemContext = $systemContext;
        }
        return $this->systemContext;
    }
    // ... handle other context types
}
```

**Key Points:**

- ✅ `hasRole('admin', null)` checks system context
- ✅ Super-admin automatically passes all role checks
- ✅ Hierarchical: Checks parent contexts too
- ✅ Cached: In-memory cache prevents repeated DB queries
- ✅ Context-aware: Supports organizational hierarchies

---

## Redirect Flow Examples

### Example 1: Regular User Login

```
1. User visits /auth/login
2. Enters credentials
3. LoginUserHandler->handle() → Returns User object
4. LoginController checks: $user->hasRole('admin') → FALSE
5. $defaultRoute = 'home'
6. No url.intended in session
7. Redirect to route('home') → /
```

### Example 2: Admin User Login

```
1. User visits /auth/login
2. Enters admin credentials
3. LoginUserHandler->handle() → Returns User object
4. LoginController checks: $user->hasRole('admin') → TRUE
5. route_exists('admin.dashboard') → FALSE
6. route_exists('admin.pages.index') → TRUE
7. $defaultRoute = 'admin.pages.index'
8. No url.intended in session
9. Redirect to route('admin.pages.index') → /admin/pages
```

### Example 3: User Tries to Access Protected Page

```
1. Unauthenticated user visits /profile
2. Authenticate middleware intercepts
3. Session: url.intended = '/profile'
4. Redirect to /auth/login
5. User logs in
6. LoginController: redirect()->intended(route('home'))
7. Session HAS url.intended = '/profile'
8. Redirect to /profile ✅
```

### Example 4: User Tries to Access Admin Page (FIXED!)

**Old Behavior (BUG):**

```
1. Unauthenticated user visits /admin/blocks/types
2. Authenticate middleware: url.intended = '/admin/blocks/types' ❌
3. Redirect to /auth/login
4. User logs in
5. Redirect to /admin/blocks/types ❌ (Broken! User không có quyền)
```

**New Behavior (FIXED):**

```
1. Unauthenticated user visits /admin/blocks/types
2. Authenticate middleware checks: str_starts_with('/admin/blocks/types', '/admin') → TRUE
3. KHÔNG lưu url.intended ✅
4. Redirect to /auth/login
5. User logs in (regular user)
6. $defaultRoute = 'home'
7. No url.intended in session
8. Redirect to / ✅ (Correct!)
```

### Example 5: Admin Tries to Access Admin Page

```
1. Unauthenticated admin visits /admin/blocks/types
2. Authenticate middleware: KHÔNG lưu url.intended
3. Redirect to /auth/login
4. Admin logs in
5. $user->hasRole('admin') → TRUE
6. $defaultRoute = 'admin.pages.index'
7. No url.intended in session
8. Redirect to /admin/pages ✅ (Admin dashboard, not random admin URL)
```

---

## Routes Overview

### Current Route Structure

| Route Name           | Path                  | Purpose         | Protected       |
| -------------------- | --------------------- | --------------- | --------------- |
| `home`               | `/`                   | Homepage        | ❌ Public       |
| `auth.login.view`    | `/auth/login`         | Login form      | ❌ Public       |
| `auth.login`         | `/auth/login` (POST)  | Handle login    | ❌ Public       |
| `auth.logout`        | `/auth/logout` (POST) | Handle logout   | ✅ Auth         |
| `admin.pages.index`  | `/admin/pages`        | Admin dashboard | ✅ Auth + Admin |
| `admin.blocks.types` | `/admin/blocks/types` | Block types API | ✅ Auth + Admin |

### Expected Routes (Future)

| Route Name        | Path         | Purpose              |
| ----------------- | ------------ | -------------------- |
| `admin.dashboard` | `/admin`     | Main admin dashboard |
| `user.dashboard`  | `/dashboard` | User dashboard       |
| `user.profile`    | `/profile`   | User profile         |

---

## Testing Scenarios

### Test 1: Regular User Login

```bash
# Setup
1. Logout if logged in
2. Visit /auth/login
3. Login with regular user (email: user@example.com)

# Expected
✅ Redirect to / (home)
❌ NOT redirect to /admin/...
```

### Test 2: Admin User Login

```bash
# Setup
1. Logout if logged in
2. Visit /auth/login
3. Login with admin user (email: admin@example.com)

# Expected
✅ Redirect to /admin/pages (or /admin/dashboard if exists)
❌ NOT redirect to / (home)
```

### Test 3: Protected Page Redirect

```bash
# Setup
1. Logout if logged in
2. Visit /profile (protected page)
3. Should redirect to /auth/login
4. Login with any user

# Expected
✅ Redirect BACK to /profile
❌ NOT redirect to home or admin
```

### Test 4: Admin Page Access (Main Bug Fix)

```bash
# Setup
1. Logout if logged in
2. Visit /admin/blocks/types
3. Should redirect to /auth/login
4. Login with REGULAR user

# Expected
✅ Redirect to / (home) - NOT to /admin/blocks/types
❌ NOT get access denied error
❌ NOT redirect to admin page
```

### Test 5: Admin Page Access by Admin

```bash
# Setup
1. Logout if logged in
2. Visit /admin/blocks/types
3. Should redirect to /auth/login
4. Login with ADMIN user

# Expected
✅ Redirect to /admin/pages (admin dashboard)
❌ NOT redirect to /admin/blocks/types (random admin URL)
```

---

## Security Considerations

### 1. **No Admin URL Leakage**

- Admin URLs không được lưu vào session
- Unauthenticated users không thể "bookmark" admin URLs qua intended mechanism

### 2. **Role-Based Routing**

- Admin users tự động được redirect đến admin area
- Regular users được redirect đến public area
- No manual role checking needed in views

### 3. **Graceful Fallback**

- Nếu admin routes chưa setup → Fallback to home
- Nếu route không tồn tại → Không crash, dùng default
- Safe với phát triển dần dần (admin dashboard có thể thêm sau)

### 4. **Context-Aware Permissions**

- `hasRole()` check trong system context
- Hỗ trợ hierarchical contexts (organizations, teams, etc.)
- Super-admin bypass tất cả checks

---

## Performance

### Caching Strategy

**1. In-Request Cache:**

```php
// AccessControlService
private array $permissionCache = [];  // User permissions
private array $resolvedContextCache = [];  // Resolved contexts
private static array $reflectionCache = [];  // Reflection methods
```

**2. Persistent Cache:**

```php
$cacheKey = "acl:all_perms:{$user->id}";
$cachedPermissions = $this->cacheStore->get($cacheKey);
```

**3. Route Resolution:**

```php
// UrlGenerator uses Router cache
$route = $this->router->getByName($name);
```

**Benefits:**

- ✅ Single DB query per user per request
- ✅ Role checks are O(1) after first load
- ✅ Context resolution cached
- ✅ Route checks cached

---

## Troubleshooting

### Issue 1: Always Redirect to Home (Even for Admin)

**Cause:** Admin role không tồn tại hoặc không được assign

**Solution:**

```bash
# Check roles in database
SELECT * FROM roles WHERE name IN ('admin', 'super_admin');

# Check user role assignments
SELECT * FROM role_assignments WHERE user_id = 1;

# Re-seed roles if needed
php cli db:seed --class=RoleSeeder
```

### Issue 2: RouteNotFoundException

**Cause:** Route 'admin.dashboard' or 'admin.pages.index' chưa được define

**Solution:**

```php
// Check routes
php cli route:list | grep admin

// Or check controller has Route attribute
#[Route('/admin/pages', ...)]
```

### Issue 3: Redirect Loop

**Cause:** Middleware stack hoặc session issue

**Solution:**

```bash
# Clear sessions
docker exec bault_app rm -rf storage/framework/sessions/*

# Clear cache
docker exec bault_app php cli cache:clear

# Restart server
docker restart bault_app
```

### Issue 4: hasRole() Always Returns False

**Cause:** Permissions chưa được load hoặc cache issue

**Solution:**

```php
// Debug in LoginController
if ($user->hasRole('admin')) {
    logger()->debug('User has admin role', ['user_id' => $user->id]);
} else {
    logger()->debug('User does NOT have admin role', [
        'user_id' => $user->id,
        'roles' => $user->getRoles()
    ]);
}
```

---

## Future Enhancements

### 1. **Admin Dashboard Route**

```php
// Create AdminDashboardController
#[Route('/admin', method: 'GET', name: 'admin.dashboard')]
public function index(): Response
{
    return response(view('admin.dashboard'));
}
```

### 2. **User Dashboard**

```php
#[Route('/dashboard', method: 'GET', name: 'user.dashboard')]
public function dashboard(): Response
{
    return response(view('user.dashboard'));
}
```

### 3. **Remember Last Admin Page**

```php
// In admin controllers, save last visited page
$this->session->set('admin.last_page', $request->getUri()->getPath());

// In LoginController, use it
if ($user->hasRole('admin')) {
    $lastAdminPage = $this->session->get('admin.last_page');
    if ($lastAdminPage && route_exists($lastAdminPage)) {
        return redirect($lastAdminPage);
    }
}
```

### 4. **Multi-Tenancy Support**

```php
// Check role in organization context
if ($user->hasRole('admin', $organization)) {
    // Organization admin
} else {
    // Regular organization member
}
```

---

## Code References

### Files Modified

1. **`src/Http/Middleware/Authenticate.php`**
   - Added admin URL exclusion from intended
   - Lines 37-42

2. **`Modules/User/Http/Controllers/Auth/LoginController.php`**
   - Added role-based redirect logic
   - Lines 49-63

3. **`src/Core/helpers.php`**
   - Added `route_exists()` helper
   - Lines 414-430

### Dependencies

- `Core\Routing\UrlGenerator` - Route URL generation
- `Core\Routing\Router` - Route registry
- `Modules\User\Domain\Services\AccessControlService` - Role checking
- `Modules\User\Infrastructure\Models\User` - User model
- `Core\Contracts\Session\SessionInterface` - Session management

---

## Summary

**Problem:**

- ❌ User bị redirect đến `/admin/blocks/types` sau login
- ❌ Không phân biệt admin và user thường
- ❌ Admin URLs được lưu vào intended redirect

**Solution:**

- ✅ Middleware không lưu admin URLs vào intended
- ✅ LoginController phân biệt redirect theo role
- ✅ Helper `route_exists()` cho safe routing
- ✅ Context-aware role checking
- ✅ Graceful fallback nếu routes chưa setup

**Impact:**

- ✅ Better UX: Users và Admins đều đến đúng nơi
- ✅ Security: No admin URL leakage
- ✅ Maintainable: Easy to add new roles/routes
- ✅ Performance: Cached role checks

---

**Last Updated:** 2025-10-27  
**Version:** 1.0.0  
**Status:** ✅ Production Ready
