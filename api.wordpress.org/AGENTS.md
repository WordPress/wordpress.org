# Agent instructions — api.wordpress.org/

Everything under `public_html/` is served from `https://api.wordpress.org/`, and PHP there executes when requested, including test files and bootstraps. Keep those inert on a direct request: no reading request input, no side effects at include time.

`public_html/init.php`, `wp-init.php` and `wp-init-ondemand.php` live in the private dotorg repository. Endpoints require them unconditionally; tests must run without them, so test bootstraps include them only behind a `file_exists()` check.

## Patterns API tests

`public_html/patterns/1.0/tests/` holds end-to-end tests that send real requests to the Patterns API and assert on production pattern data. No CI job runs them, so run them yourself:

-   When changing anything in `public_html/patterns/`.
-   Before committing a pattern-directory change that affects the `wporg-pattern` REST response: its fields, meta, categories, keywords, or search. Run them on a sandbox with the change applied: when `WPORG_SANDBOXED` is set, the bootstrap sends requests to the sandbox's API instead of production.
-   After deploying such a change, against production.

Run from the repository root, after `composer install`:

```
vendor/bin/phpunit -c api.wordpress.org/public_html/patterns/1.0/phpunit.xml.dist
```

`test_search_locale_sort` is expected to be incomplete until https://github.com/WordPress/pattern-directory/issues/347 is fixed.

A failure is either a regression or a change in production data, such as an edited or deleted pattern. Check which before changing code: a data change means updating the test case, not the endpoint.
