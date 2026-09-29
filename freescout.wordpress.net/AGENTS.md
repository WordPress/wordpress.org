# AGENTS.md — freescout.wordpress.net

Context for AI coding agents and developers working on the WordPress.org FreeScout helpdesk. Read the [root AGENTS.md](../AGENTS.md) first; this file only covers what is specific to FreeScout.

---

## What Lives Here

[FreeScout](https://github.com/freescout-help-desk/freescout) is a Laravel helpdesk. This folder contains **only our own FreeScout modules**, never FreeScout core:

- `Modules/<Name>/` — one directory per module (`module.json`, `Providers/`, `Http/`, `Resources/`, `Database/Migrations/`, `Public/`, `tests/`).
- `tests/` — shared PHPUnit bootstrap and base `TestCase`.

Premium (paid) modules must never be committed here.

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

Tests run against a real FreeScout and database, using `phpunit.xml.dist` and the shared harness in `tests/`. Modules aren't active in the test database, so each test registers the provider under test (`$this->app->register( … )`).

---

## Module Conventions

- **Naming:** prefix modules with `WPOrg` (directory `WPOrgSidebar`, alias `wporgsidebar`). Aliases are lowercase, unique, and must never change — they key the module's DB state, options, views (`wporgsidebar::view`), and public asset path.
- **`authorUrl` / `detailsUrl`:** never point these at `freescout.net`. Core treats such modules as official and requires a paid license activation.
- **Activation state** lives in the `modules` DB table; the `active` field in `module.json` is ignored by core.
- **New module:** add its directory under `Modules/`. Once it's deployed, an admin switches it on under Manage » Modules.
- **After changing module files:** run `php artisan freescout:clear-cache`.
- **Routes:** register them in the provider with `loadRoutesFrom()`, and pass URLs to JavaScript through `data-` attributes rather than FreeScout's generated laroute files.

---

## Coding Standards

This folder has its own standard, [`phpcs.xml.dist`](phpcs.xml.dist); the root ruleset excludes it. Run it from the repository root with `vendor/bin/phpcs --standard=freescout.wordpress.net/phpcs.xml.dist`. It's the WordPress Coding Standards, plus:

- `declare( strict_types = 1 );` is enforced. Type every parameter and return value.
- PHP compatibility is checked against PHP 8.3.
- No sniffs that need WordPress: file names (PSR-4 instead), WordPress API alternatives, escaping, sanitizing, and nonces. Escape output with Blade's `{{ }}` or `e()`.
