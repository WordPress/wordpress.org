# Agent instructions — wordpress.org/public_html/style/

Everything in this directory is served publicly, as-is, from `https://s.w.org/style/`.
Changes are committed to meta.svn and then deployed from a Dotorg sandbox — committing alone
does not make them live. See README.md for the full file inventory and development flow, and
trac/README.md for the Trac-specific workflow.

## Commands

Run from this directory (`npm install` first):

-   `npm run build` — rebuild all generated files
-   `npm run build:css` — regenerate `wp4-rtl.css` from `wp4.css`
-   `npm run build:js` — rebuild `js/navigation.min.js`
-   `npm run format` — Prettier over all sources
-   `npm run lint:js` — ESLint (stock `@wordpress/scripts` ruleset) over `js/` and `trac/`

## Rules

-   Never hand-edit generated files: `wp4-rtl.css`, `js/navigation.min.js`. Edit the source
    (`wp4.css`, `js/navigation.js`), rebuild, and commit source and output together.
-   Write any vendor prefix a property still needs by hand — supported browsers
    (`@wordpress/browserslist-config`) rarely require them. After editing `wp4.css`, run
    `npm run build:css` to keep `wp4-rtl.css` in sync.
-   Never edit vendored files: `trac/common/`, `trac/jquery.atwho.min.js`,
    `trac/jquery.caret.min.js`. They are excluded from linting and formatting.
-   Code style is the stock `@wordpress/scripts` configuration with one override:
    `printWidth: 120` (`.prettierrc.js`). Run `npm run format` and `npm run lint:js` before
    committing; both must pass clean.
-   The files in `trac/` run live on `*.trac.wordpress.org` with no build step. Once a change
    to them is committed AND deployed from a Dotorg sandbox, `scripts_version` must be bumped
    in BOTH `trac.wordpress.org/templates/site_head.html` and `site_footer.html` in a
    follow-up commit, or browsers keep serving the cached old version. The bump itself is
    picked up automatically by the hosts' SVN refreshes. Details in trac/README.md.
