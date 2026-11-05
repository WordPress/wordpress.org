# WordPress.tv Theme (wptv-27)

Block theme for [WordPress.tv](https://wordpress.tv/), the WordPress community site that publishes videos from WordCamps, meetups and other community events.

wptv-27 replaces the legacy classic theme (`wptv2`). It is built with Full Site Editing (block templates, template parts, patterns and `theme.json`), and relies on WordPress core features instead of hand-written code wherever possible.

> **Status:** Release 1 in development, theme version 0.4.2. The block-theme layouts, automatic WordCampTV landing, locked video editor, anonymous submission form and per-field moderation are implemented. R1/R2/R4 security fixes and upload-format changes are integrated and passed focused Studio checks with imported data. The latest full 145-URL comparison predates those fixes: all recorded route/query fields match, but it is **not an exact-match pass** because four poster-related feed hashes remain unaccepted; four API hashes have existing allowances. A post-fix route run remains pending authorization.
>
> **Release 1 establishes a conservative block-theme baseline in production**, preserving existing behavior except for explicitly approved changes. Uploads have already been tested and do not work on the Atomic test site, so that environment cannot validate the complete upload path. Final platform-dependent checks must take place in production, in an owner-approved window with rollback to `wptv2` and an agreed observation period. That baseline is not established yet; further functional development follows it. Subtitle player integration, upload and incoming-subtitle management are deferred to the next delivery. Git publication is not deployment. See the [regression report](docs/regression-editor-checkpoint.md), [session handoff](docs/handoff.md) and [project plan](docs/plan.md).

## Constraints

WordPress.tv runs on the WordPress.com multisite. That means:

- No custom plugins can be installed. Only the plugins available on the multisite can be used, so every site-specific feature lives in this theme.
- Videos are hosted on VideoPress.
- Several VideoPress helpers only exist on WordPress.com. They are always called behind `function_exists()` checks, so the theme also runs in local environments.

See [`docs/wpcom-constraints.md`](docs/wpcom-constraints.md) for details.

## Repository layout

| Path | Purpose | In theme zip |
|---|---|---|
| `AGENTS.md` | Entry point for AI coding agents: project rules and documentation index | No |
| `docs/` | Project documentation: handoff, plan, route inventory, legacy analysis, decisions | No |
| `tests/` | Local-only block/API checks and route regression tooling | No |
| `composer.json`, `phpcs.xml.dist` | WordPress Coding Standards (`composer lint`); dependencies install to the ignored `vendor/` | No |
| `blueprint.json` | Empty Playground/Studio review site for standards, security and WP-CLI checks | No |
| Everything else | The theme itself | Yes |

## Working on the theme

Clone the full repository into the `wp-content/themes/` directory of a local WordPress site (for example, a [WordPress Studio](https://developer.wordpress.com/studio/) site):

```bash
cd wp-content/themes
git clone https://github.com/nilovelez/wptv-27.git wptv-27
```

Read [`AGENTS.md`](AGENTS.md) and the [`docs/`](docs/) folder before making changes. The most important rule: **existing URLs must not change**. See [`docs/routes.md`](docs/routes.md).

## Building a clean theme zip

Development-only files (`docs/`, `tests/`, `AGENTS.md`, `.gitattributes`, `.gitignore`, `.github/`, `blueprint.json`, `composer.json`, `composer.lock`, `phpcs.xml.dist`) are marked `export-ignore` in [`.gitattributes`](.gitattributes), so `git archive` leaves them out.

From the repository root:

```bash
git archive --format=zip --prefix=wptv-27/ --output=wptv-27.zip HEAD
```

- `--prefix=wptv-27/` puts the files inside a `wptv-27/` folder, which is what WordPress expects when you upload a theme zip.
- Replace `HEAD` with a tag or branch name to export a specific version, for example `v1.0.0`.
- `git archive` only exports **committed** files and reads `export-ignore` rules from the committed `.gitattributes`. Commit your changes before you build the zip.

To check what the zip will contain without creating it:

```bash
git archive --format=tar HEAD | tar -t
```

You can also download a clean zip straight from GitHub (**Code → Download ZIP**, or the source archive of any release), because GitHub honors `export-ignore` too. The folder inside that zip is named after the repository and branch, so rename it to `wptv-27` before you upload it.

## Language

All project content is written in **US English (en_US)**, following the WordPress standard: code, function names, comments, translatable strings, commit messages and documentation.

## License

GNU General Public License v2 or later.
