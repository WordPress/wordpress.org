# WordPress.org Trac Customizations

Client-side customizations for the WordPress.org Trac installs
(`core.trac.wordpress.org`, `meta.trac.wordpress.org`, `bbpress.trac.wordpress.org`,
`buddypress.trac.wordpress.org`), served from `https://s.w.org/style/trac/`.

These files are plain static assets — there is no build step. They are loaded
by the Jinja2 site templates in `trac.wordpress.org/templates/`, which Trac
auto-includes on every page (`site_head.html` for styles, `site_footer.html`
for scripts).

## Files

| File                                         | Loaded by                             | Description                                                                                                                                                                                                                                              |
| -------------------------------------------- | ------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `wp-trac.js`                                 | `site_footer.html`, every page        | Main customization script: workflow tweaks, keyword management, ticket form behavior, @-mention autocomplete, and more.                                                                                                                                  |
| `wp-trac.css`                                | `site_head.html`, every page          | Main customization stylesheet.                                                                                                                                                                                                                           |
| `wp-trac-jinja-compat.js`                    | `site_footer.html`, every page        | Client-side port of the DOM rewrites the old Genshi `site.html` did server-side with `py:match`; needed since the Trac 1.6 upgrade (Jinja2 can only add markup, not rewrite it). To be merged into `wp-trac.js` once verified on the live instance.      |
| `wp-trac-jinja-compat.css`                   | `site_head.html`, every page          | CSS half of the Genshi `py:match` port. To be merged into `wp-trac.css` once verified.                                                                                                                                                                   |
| `trac-security.js`                           | `site_footer.html`, `/newticket` only | Warns reporters when a new ticket looks like a security report.                                                                                                                                                                                          |
| `jquery.atwho.min.js`, `jquery.caret.min.js` | `site_footer.html`, every page        | Vendored libraries powering @-mention autocomplete. Not linted, not formatted.                                                                                                                                                                           |
| `common/`                                    | Trac itself                           | Copy of Trac's stock static assets (`htdocs`). Served via `htdocs_location` in `trac.wordpress.org/conf/common.ini`; Trac generates all links to it. `common/trac.ico` is also referenced directly as the favicon. Vendored — not linted, not formatted. |

Linting and formatting run from the parent directory: `npm run lint:js` and
`npm run format` in `style/` cover `trac/` as well.

## Testing changes

There is no staging Trac, but since these are plain files you can test the
exact bytes that will ship using your browser's developer tools:

-   **Local overrides (recommended):** in Chrome DevTools, open _Sources →
    Overrides_, enable overrides for a local folder, then edit (or paste your
    modified copy over) `https://s.w.org/style/trac/wp-trac.js` — reloads on
    any `*.trac.wordpress.org` page now use your local version. Firefox offers
    the same via _Local Overrides_ in the Network panel.
-   **Console:** for quick one-offs, paste the modified file's contents into
    the DevTools console on a Trac page. Most of the code runs inside
    `jQuery( function() {} )` ready callbacks, which execute immediately when
    registered after page load.

## Deploying and bumping the script version

Every include of these files carries a cache-busting query string,
`?${scripts_version}`, defined at the top of **both**
`trac.wordpress.org/templates/site_head.html` and
`trac.wordpress.org/templates/site_footer.html`. (Both definitions are
needed: Trac includes the two templates separately, and Jinja2 `set`
variables don't cross include boundaries.)

Deployment is a two-step flow:

1. **Commit the JS/CSS changes to meta.svn and deploy them from a Dotorg
   sandbox, as usual.** Even once deployed, browsers keep using their cached
   copy, because the URL (including `?scripts_version`) hasn't changed.
2. **Bump `scripts_version` in a follow-up commit** — in `site_head.html`
   _and_ `site_footer.html` — once the assets are deployed. The bump needs no
   deploy of its own: the Trac hosts pick the template change up through
   their automatic SVN refresh. Bumping only _after_ the assets are live
   matters: bump too early and the CDN caches the stale file under the new
   version string.
