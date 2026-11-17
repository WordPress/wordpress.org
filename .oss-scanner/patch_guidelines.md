# How we would like patches made

## Size and scope of a fix

- As narrow as the defect allows: change the faulty code, with no refactoring and nothing beyond the fix.
- When the same defect is on a twin path (the REST and AJAX versions of one action, a feed and a page), fix every
  twin in the same patch.
- Escape at the point of output, with the function for that context (`esc_html()`, `esc_attr()`, `esc_url()`,
  `wp_kses()` and friends), not by sanitizing in a getter. Use `$wpdb->prepare()` for SQL, `current_user_can()` with
  the object's ID for per-object checks, `check_admin_referer()` or `wp_verify_nonce()` for nonces, and
  `escapeshellarg()` for shell arguments.
- Never change third-party code under `vendor/` or `libs/`. When the fix belongs in an upstream project (WordPress
  core, bbPress, GlotPress, Jetpack), say so instead.
- Some directories are mirrored from other repositories, such as `wordpress.org/public_html/wp-content/plugins/wporg-learn`.
  If `git log` shows sync commits for the file, say which upstream repository the fix belongs in.

## Code style

- The WordPress coding standards, as `phpcs.xml.dist` configures them: `/src/vendor/bin/phpcs <file>` must report
  nothing new on the lines the patch touches. Tabs for indentation.
- Comments only where the code cannot speak for itself, one line wherever possible. Docblocks on new functions.
- A new PHP file declares `declare( strict_types = 1 );` after its file docblock; existing files are not converted.
- A change to a FreeScout module under `freescout.wordpress.net/Modules/` bumps the `version` in its `module.json`.
- American English in strings and comments.

## Tests

- Add a regression test only where the code already has a PHPUnit suite (see the `*:test` scripts in
  `environments/package.json` and `.github/unit-tests-suites.yml`), next to the existing tests for that code, and run
  that suite. New test files use the long `array()` syntax.
- Otherwise, give the manual steps that show the fix works.

## Branch and commit message

- A `git diff` against `trunk` in `/src`.
- The repository is a mirror of meta.svn.wordpress.org, and fixes land as Subversion commits. Write the commit message
  subject as "Component: Summary", imperative, under 100 characters, saying what the fix protects rather than only what
  the diff does, for example "Plugin Directory: Escape readme contributor names on the plugin page".
