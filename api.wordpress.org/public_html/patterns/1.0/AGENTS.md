# Agent instructions — Patterns API

This directory is served from `https://api.wordpress.org/patterns/1.0/`, and PHP here executes when requested, including `tests/`. Keep the tests and their bootstrap inert on a direct request: no reading request input, no side effects at include time.

`api.wordpress.org/public_html/init.php` and `wp-init.php` live in the private dotorg repository. `index.php` requires `wp-init.php` unconditionally; the tests must run without either, so the bootstrap includes `init.php` only behind a `file_exists()` check.

## Tests

`tests/` holds end-to-end tests that send real requests to the Patterns API and assert on production pattern data. No CI job runs them, so run them yourself:

-   When changing anything in this directory.
-   Before committing a pattern-directory change that affects the `wporg-pattern` REST response: its fields, meta, categories, keywords, or search. Run them on a sandbox with the change applied: when `WPORG_SANDBOXED` is set, the bootstrap sends requests to the sandbox's API instead of production.
-   After deploying such a change, against production.

Run from the repository root, after `composer install`:

```
vendor/bin/phpunit -c api.wordpress.org/public_html/patterns/1.0/phpunit.xml.dist
```

`test_search_locale_sort` is expected to be incomplete until https://github.com/WordPress/pattern-directory/issues/347 is fixed.

A failure is either a regression or a change in production data, such as an edited or deleted pattern. Check which before changing code: a data change means updating the test case, not the endpoint.
