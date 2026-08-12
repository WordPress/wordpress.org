# WordPress.org Meta

This repository powers production WordPress.org sites (wordpress.org, api.wordpress.org, login, forums, translate, and others). It runs on WordPress `trunk` and targets PHP 8.4 compatibility. For broader architecture and workspace context, see [AGENTS.md](../AGENTS.md).

## Security practices

Most code here handles untrusted input on a high-traffic, high-value target. Hold every change to these expectations:

- **Late escaping.** Output must be escaped at the point of echo, with the context-appropriate function (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`). Escaping at save time is not a substitute.
- **Prepared SQL.** Any variable interpolated into a query must go through `$wpdb->prepare()`. Table names concatenated from constants are acceptable; user-influenced values never are.
- **Sanitized superglobals.** `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, and `$_SERVER` values must be sanitized on read (`sanitize_text_field()`, `absint()`, `wp_unslash()`, type casts). Cookie and header values may be arrays or absent — watch for type confusion.
- **Nonce and capability checks.** State-changing actions need both a nonce verification and a `current_user_can()` check. A nonce alone is not authorization.
- **REST endpoints.** Every route needs an explicit `permission_callback`. Argument definitions should validate and sanitize; note that a custom `sanitize_callback` disables the default schema validation.
- **SSRF and remote requests.** URLs passed to `wp_remote_*()` that derive from user input need validation against expected hosts.
- **Object injection.** `unserialize()` on any value that could be user-influenced is a vulnerability; prefer JSON.
- **Signature and secret comparison.** Webhook handlers and API callbacks that verify shared secrets or HMAC signatures must compare with `hash_equals()`, never `==` or `===` — string comparison leaks timing information.
- **Test isolation.** In PHPUnit tests, `remove_all_filters()` and `remove_all_actions()` strip production callbacks along with test ones. Tests must remove the specific callback they added.

## Repository quirks

- `wp-init-ondemand.php` is required by many API endpoints but lives in a private repository. Its absence here is expected; do not report the include as broken or suggest creating the file.
- Code that overrides `$wp_object_cache->blog_prefix` or switches blogs before cache/database access is an intentional multisite pattern, not a bug.
- Direct database queries and low-level cache manipulation are common and accepted in the `api.wordpress.org` endpoints, which run outside a full WordPress bootstrap.

## When reviewing pull requests

- Do not comment on formatting, alignment, Yoda conditions, or other mechanical style issues. Code style is enforced by a PHP_CodeSniffer CI job against the repository's `phpcs.xml.dist`.
- This Git repository is a read-only mirror of `meta.svn.wordpress.org`. PRs are not merged on GitHub; a Meta committer commits the patch to SVN, which closes the PR automatically. Do not suggest merge-related workflow steps.
