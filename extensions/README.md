# Server extensions

Drop server-side extensions here. They load at every request, before routing, so
they can register hooks, routes and database tables without touching core.

This folder is intentionally empty in git: everything you add is ignored (see
`.gitignore`). An extension is local to one install.

## How it loads

- `extensions/name.php` is loaded directly.
- `extensions/name/extension.php` is loaded (use a folder when you ship extra
  files: SQL, assets, helper classes).

Dotfiles are skipped. A fatal in one extension is logged and does not take the
site down.

## What an extension can do

Register listeners on core hooks via `Hook::listen($event, $callback, $priority)`.

- `Hook::fire($e, ...$args)` runs an action (side effects only).
- `Hook::filter($e, $value, ...$ctx)` threads a value through listeners; each
  returns the new value.
- `Hook::until($e, ...$args)` stops at the first listener returning non-null
  (middleware / override).

### Hooks fired by core

| Event | Type | Where | Use for |
|-------|------|-------|---------|
| `request.before` | fire | start of every public + api request | IP/abuse blockers, CAPTCHA gate, maintenance |
| `route.public` | until | public router, before payload serve | custom public pages (return true if handled) |
| `route.api` | until | api router, before 404 | custom `/api/...` endpoints (return true if handled) |
| `auth.signup` | filter | registration | validate/transform signup (CAPTCHA, email); throw to reject |
| `auth.login` | fire | after successful login | per-rank side effects, welcome back |
| `user.created` | fire | after a user is created | assign rank, send credentials |
| `payload.before_create` | fire | before a payload is created | enforce quota (throw to block) |
| `report.before_store` | filter | callback ingest, before store | enrich (geo), or return null to drop |
| `report.stored` | fire | after a report is stored | post-store side effects |
| `report.view` | filter | single report fetch | mask fields by rank (data retention) |
| `alert.report` | fire | alert dispatch | observe a report before alerts |
| `alert.mail` | until | mail alert transport | route mail through an external service (return true to skip default) |
| `migrate` | fire | migrator run (`$db` arg) | create your own tables |

Roles and capabilities are native: make a role (e.g. `pro`) in the panel and tick
the capabilities it needs. Extensions read `$user['role']` for numeric tiers
(quota, retention) that a boolean capability cannot express.

### Example

```php
<?php
// extensions/geo.php - add Cloudflare geo to each report's meta
Hook::listen('report.before_store', function ($data) {
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $data->ez_geo = array(
            'country' => $_SERVER['HTTP_CF_IPCOUNTRY'],
            'city'    => $_SERVER['HTTP_CF_IPCITY'] ?? '',
        );
    }
    return $data;
});
```

Read config/secrets from `.env` via `Config::get('MY_KEY')`; never hardcode keys.
Scripts added via `panel.scripts` are served in the public panel bundle, so keep
secrets server-side (in `extension.php`), never in client JS.
