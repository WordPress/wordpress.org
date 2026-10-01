# AGENTS.md — freescout.wordpress.net

Context for AI coding agents and developers working on the WordPress.org FreeScout helpdesk. Read the [root AGENTS.md](../AGENTS.md) first; this file only covers what is specific to FreeScout.

---

## What Lives Here

[FreeScout](https://github.com/freescout-help-desk/freescout) is a Laravel helpdesk. This folder contains **only our own FreeScout modules**, never FreeScout core:

- `Modules/<Name>/` — one directory per module (`module.json`, `Providers/`, `Http/`, `Resources/`, `Database/Migrations/`, `Public/`, `tests/`).
  - `WPOrgSidebar` — WordPress.org panels in the conversation sidebar, loaded over AJAX from `api.wordpress.org/dotorg/freescout/`.
  - `WPOrgWebhooks` — queues conversation events to `api.wordpress.org/dotorg/freescout/webhook.php`, which records contributor stats.
  - `WPOrgSSO` — logs agents in through login.wordpress.org's SAML identity provider (wp-saml-idp, in the private dotorg repository), and connects every user to a WordPress.org account. Its SAML library is committed in its `vendor/`: FreeScout doesn't install module dependencies. After changing its `composer.json`, run `composer install --no-dev` in the module and commit `vendor/` (`git add -f`: the root `.gitignore` ignores it).
  - `WPOrgSite` — tweaks for how WordPress.org runs FreeScout, rather than features; add new ones here instead of starting a module. So far, it refuses to update or delete modules from the Modules page, and lays its cards out in columns.
- `tests/` — shared PHPUnit bootstrap and base `TestCase`.

Premium (paid) modules must never be committed here.

The api.wordpress.org side lives in `api.wordpress.org/public_html/dotorg/freescout/`. Requests are JSON, signed with an HMAC-SHA256 of the body in `X-FreeScout-Signature` (shared secret: `WPORG_API_SECRET` here, `FREESCOUT_SECRET` there) and rejected after 15 minutes. `WPOrgSidebar/Services/ConversationPayload.php` and `WPOrgWebhooks/Services/EventPayload.php` define what's sent; change them together with the endpoints that read them. The sidebar inserts the endpoints' HTML as-is, so they must escape everything they output. Mark it up with the `wporg-sidebar-*` classes that `WPOrgSidebar/Public/css/sidebar.css` styles, statuses with `render_badge()`, and section sizes with `render_count()`, instead of inline styles; the local mock's sample panels use the same markup.

"Customer" is FreeScout's term (`App\Customer`); in our own names and text, use "sender".

---

## FreeScout Core Is Not Ours

> [!IMPORTANT]
> The systems team installs and updates FreeScout core on production **independently of this repository**, on their own schedule. We do not pin, patch, or deploy core. Every module here must keep working when core changes underneath it.

### Why this matters

A module that breaks after a core update can take down the **entire helpdesk**, not just itself:

- An `\Exception` thrown while a module registers (its provider's `register()`, or its files) is logged and skipped on page loads and CLI, but re-thrown on form submissions, so actions like sending a reply fail. FreeScout only deactivates the module if that happens during activation from the admin UI, or for a "No such file or directory" error (see `modules.register_error` in core's `AppServiceProvider`).
- Anything thrown from a provider's `boot()` isn't caught at all, and fails every request.
- Core catches `\Exception` only. A PHP `\Error` (`Error`, `TypeError`, `ArgumentCountError` — e.g. a core class, method, or signature that changed) is never caught.
- Exceptions thrown later, inside hook callbacks, are not caught by core at all.
- `requiredAppVersion` in `module.json` is **advisory only**: the admin UI shows a warning, but nothing prevents the module from loading on an older core.

### Rules for every module

1. **Integrate only through public extension points.** Use Eventy hooks (`\Eventy::addAction()` / `\Eventy::addFilter()`, `@action` / `@filter` in Blade), public model methods, `\Option`, and meta helpers. Do not override core views, extend or depend on classes under core's `overrides/`, or reach into private/protected internals. The test harness is the exception: it extends core's `TestCase`, since a core change there can only break the tests, not the helpdesk.
2. **Fail soft in the service provider.** Wrap anything in `boot()` / `register()` that touches core APIs beyond hook registration in `try { … } catch ( \Throwable $e ) { \Log::error( … ); }` so a failure disables the feature, not the site.
3. **Fail soft in hook callbacks.** Callbacks that call external services (e.g. WordPress.org APIs) or less stable core APIs must catch `\Throwable`, log, and return the unmodified value (filters) or render nothing (actions).
4. **Feature-detect instead of assuming a version.** Guard calls to newer core APIs with `class_exists()` / `method_exists()`, or compare against `config( 'app.version' )` via `\Helper::checkAppVersion()`. Keep `requiredAppVersion` accurate anyway, as documentation.
5. **Never hard-depend on a premium module.** Most contributors don't have premium modules locally. Check that a premium module is active (`\Module::isActive( 'alias' )`) before using it and degrade gracefully otherwise. Declare `requiredModules` only when the module is meaningless without the premium module.
6. **Migrations must be additive and idempotent.** Core runs `migrate --force` on every update; guard with `Schema::hasTable()` / `Schema::hasColumn()`, and never alter core tables. Register them with `$this->loadMigrationsFrom()`, so the `migrate` that deploys run (through `freescout:after-app-update`) picks them up.
7. **Switch a module off before its code is removed, and remove the code in a later deploy.** FreeScout caches its module list. If a module's code disappears while it's still on, every request fails, including every `artisan` command, until someone deletes the cache on the server.
8. **Recover from a broken update by reverting it.** A module that breaks FreeScout's boot also breaks `artisan`, so it can't be switched off. Revert the commit, redeploy, and run `php artisan freescout:after-app-update`.

### Testing against core

Tests run inside the local environment (`environments/freescout/`), against a real FreeScout and database:

```bash
npm run freescout:test                          # from environments/
npm run freescout:test -- --filter SidebarTest  # extra arguments go to PHPUnit
FREESCOUT_REF=master npm run freescout:start     # try upcoming core changes
```

`npm run freescout:logs` shows the web server and the mock API's record of every webhook event. What modules log with `\Log::error()` goes to FreeScout's app log instead (also under Manage » Logs in the UI):

```bash
npm run freescout:env -- exec app bash -c 'tail -f storage/logs/laravel-*.log'
```

Modules aren't active in the test database, so each test registers the provider under test (`$this->app->register( … )`). Registering after boot leaves module routes out of the name index; call `$this->app['router']->getRoutes()->refreshNameLookups()` before using `route()`. CI runs the suite against the latest `dist` release and `master` on every change and daily, since core updates don't come with a commit here.

---

## Module Conventions

- **Naming:** prefix modules with `WPOrg` (directory `WPOrgSidebar`, alias `wporgsidebar`). Aliases are lowercase, unique, and must never change — they key the module's DB state, options, views (`wporgsidebar::view`), and public asset path.
- **Name and icon:** `name` in `module.json` is what Manage » Modules shows (`WP.org Sidebar`), and what `module:enable` looks the module up by. `img` points at `Public/img/icon.svg`, a module-specific icon on the `#3858e9` tile.
- **`authorUrl` / `detailsUrl`:** never point these at `freescout.net`. Core treats such modules as official and requires a paid license activation.
- **Activation state** lives in the `modules` DB table; the `active` field in `module.json` is ignored by core.
- **Configuration** comes from the environment: `WPORG_API_URL` and `WPORG_API_SECRET`. Without a secret, modules stay quiet instead of failing.
- **New module:** add its directory under `Modules/`. Once it's deployed, an admin switches it on under Manage » Modules.
- **After changing module files:** run `php artisan freescout:clear-cache` (`npm run freescout:artisan -- freescout:clear-cache`).
- **Routes:** register them in the provider with `loadRoutesFrom()`, and pass URLs to JavaScript through `data-` attributes rather than FreeScout's generated laroute files.

---

## Coding Standards

This folder has its own standard, [`phpcs.xml.dist`](phpcs.xml.dist); the root ruleset excludes it. Run it from the repository root with `vendor/bin/phpcs --standard=freescout.wordpress.net/phpcs.xml.dist`. It's the WordPress Coding Standards, plus:

- `declare( strict_types = 1 );` is enforced. Type every parameter and return value.
- PHP compatibility is checked against PHP 8.3.
- No sniffs that need WordPress: file names (PSR-4 instead), WordPress API alternatives, escaping, sanitizing, and nonces. Escape output with Blade's `{{ }}` or `e()`.

JavaScript in `Modules/*/Public/js/` follows the WordPress JavaScript Coding Standards through `wp-scripts lint-js`: run `npm install` and `npm run lint:js` in this folder. FreeScout loads these files as plain scripts, with its own `jQuery`, so they're wrapped in `( function ( $ ) { … } )( jQuery );` rather than using modules.
