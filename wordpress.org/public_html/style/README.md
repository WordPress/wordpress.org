# WordPress.org Global Styles

Global stylesheets, scripts, and images for WordPress.org, served from
`https://s.w.org/style/`. Changes are committed to meta.svn and then deployed
from a Dotorg sandbox; see [Deployment](#deployment).

## Files

| File                                                                                   | Description                                                                                                                          |
| -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `wp4.css`                                                                              | The main WordPress.org stylesheet. Edited by hand; vendor prefixes are hand-written on the rare occasion a property still needs one. |
| `wp4-rtl.css`                                                                          | RTL version of `wp4.css`. Generated — do not edit by hand.                                                                           |
| `blog-wp4.css`, `codex-wp4.css`, `forum-wp4.css`, `forum-wp4-rtl.css`, `forum-ie7.css` | Standalone legacy stylesheets for individual properties. Edited by hand, not part of the build.                                      |
| `js/navigation.js`                                                                     | Navigation menu toggle and keyboard-navigation support. Source.                                                                      |
| `js/navigation.min.js`                                                                 | Minified build of `navigation.js`. Generated — do not edit by hand.                                                                  |
| `images/`, `header-logo.png`                                                           | Static images.                                                                                                                       |
| `trac/`                                                                                | Customizations for the `*.trac.wordpress.org` installs. See [trac/README.md](trac/README.md).                                        |

## Development

Tooling is based on [`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).
Requires Node.js and npm.

```
npm install
```

| Command             | Description                                                                                                                                                                                                                                               |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm run build`     | Runs `build:css` and `build:js`.                                                                                                                                                                                                                          |
| `npm run build:css` | Regenerates `wp4-rtl.css` from `wp4.css` via `bin/build-rtl.js`. The RTL pass includes a plugin that swaps Dashicons left/right arrow glyphs, which RTLCSS cannot infer from the CSS itself.                                                              |
| `npm run build:js`  | Builds `js/navigation.js` into `js/navigation.min.js` (webpack via `wp-scripts build`, see `webpack.config.js`). Transpilation targets come from the `browserslist` field in `package.json`, which extends the official `@wordpress/browserslist-config`. |
| `npm run format`    | Formats all source files with Prettier (`wp-scripts format`).                                                                                                                                                                                             |
| `npm run lint:js`   | Lints all JavaScript, including `trac/`, with the stock `@wordpress/scripts` ESLint configuration. Vendored files (`*.min.js`, `trac/common/`) are excluded.                                                                                              |

Code in this directory adheres to the default `@wordpress/scripts` style,
with one local override: `.prettierrc.js` extends the stock
`@wordpress/prettier-config` with a line length of 120. Generated files
(`wp4-rtl.css`, `js/navigation.min.js`) are committed, since
the CDN serves this directory as-is; rebuild and commit them together with
their sources.

## Deployment

Commit changes to the meta SVN repository as normal, then deploy the files
from a Dotorg sandbox — committing alone does not make them live on
`s.w.org`. Consumers reference these files with cache-busting query strings,
so a change to an existing file also needs the referencing version string
bumped once the file is deployed; the bump itself is picked up automatically
by the hosts' SVN refreshes. See [trac/README.md](trac/README.md) for how
that works for the Trac assets.
