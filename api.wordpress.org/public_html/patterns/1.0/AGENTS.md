# Agent instructions — Patterns API

This directory is served from `https://api.wordpress.org/patterns/1.0/`, and PHP here executes when requested, including `tests/`. Keep the tests and their bootstrap inert on a direct request: no reading request input, no side effects at include time.

## Tests

`tests/` holds end-to-end tests that send real requests to the Patterns API and assert on production pattern data. No CI job runs them, so run them yourself when changing anything in this directory: on a sandbox with the change applied (when `WPORG_SANDBOXED` is set, requests go to the sandbox's API instead of production), and again against production after deploying.

Run from the repository root, after `composer install`:

```
vendor/bin/phpunit -c api.wordpress.org/public_html/patterns/1.0/phpunit.xml.dist
```

`test_search_locale_sort` is expected to be incomplete until https://github.com/WordPress/pattern-directory/issues/347 is fixed.

A failure is either a regression or a change in production data, such as an edited or deleted pattern. Check which before changing code: a data change means updating the test case, not the endpoint.
