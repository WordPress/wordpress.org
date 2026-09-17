# WordPress.org Local Development Environments

Local development environments for WordPress.org projects, powered by [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

## Prerequisites

- [Docker](https://www.docker.com/products/docker-desktop/) installed and running
- [Node.js](https://nodejs.org/) — the version in [.nvmrc](.nvmrc) (`nvm use`)

## Setup

From the `environments/` directory:

```bash
npm install
```

## Available Environments

### Plugin Directory

A local instance of the WordPress.org Plugin Directory with the plugin directory plugin, theme, and supporting mu-plugins.

**Start:**

```bash
npm run plugins:env start
```

**Import a plugin by slug:**

```bash
npm run plugins:import -- akismet
```

**Re-seed plugins** (clears import flag, then re-imports):

```bash
npm run plugins:refresh
```

**Access:** `http://localhost:8888`

**WP CLI:**

```bash
npx wp-env run cli wp <command>
```

**Run tests:**

```bash
npm run plugins:test
```

### Theme Directory

A local instance of the WordPress.org Theme Directory with the theme directory plugin, Theme Check, the `wporg-themes-2024` frontend theme, and supporting mu-plugins. Themes are imported from the live WordPress.org themes API.

**Start:**

```bash
npm run themes:env start
```

**Re-import themes** (on demand, without clearing existing data):

```bash
npm run themes:import
```

**Re-seed themes** (clears import flag, then re-imports):

```bash
npm run themes:refresh
```

**Access:** `http://localhost:8888`

**WP CLI:**

```bash
npm run themes:env -- run cli -- wp <command>
```

**Run tests:**

```bash
npm run themes:test
```

### Jobs

A local instance of jobs.wordpress.net with the JobsWP plugin, theme, sample job categories, and sample job posts.

**Start:**

```bash
npm run jobs:env start
```

**Access:** `http://localhost:8888`

**WP CLI:**

```bash
npm run jobs:env -- run cli -- wp <command>
```

### Browse Happy

A local instance of [browsehappy.com](https://browsehappy.com) with the theme.

**Start:**

```bash
npm run browsehappy:env start
```

**Access:** `http://localhost:8888`

### Translate

A local instance of translate.wordpress.org with GlotPress, the `wporg-gp-*` plugins active on production, and the `pub/wporg` theme.

**Start:**

```bash
npm run translate:env start
```

First start auto-imports `hello-dolly` (plugin) and `twentytwenty` (theme) so the `WordPress Plugins` and `WordPress Themes` project containers have real fixtures. It also seeds a few demo Translation Events (active, upcoming, past, and draft) with hosts and attendees.

**Access:** `http://localhost:8888`

**Users:** `admin` / `password` is a GlotPress global administrator, so it can approve translations everywhere and never sees a permission check fail. `translator` / `password` is a plain subscriber with no GlotPress permissions — use it to check what a contributor sees, such as suggestions going to waiting instead of current. The dev login button fills in `admin`, so type the contributor credentials by hand.

**Import a plugin or theme's translations on demand:**

```bash
npm run translate:import -- plugin akismet
npm run translate:import -- theme twentytwentyfour
```

**Seed demo events on demand** (idempotent):

```bash
npm run translate:seed-events
```

**Re-seed** (clears the seed flag so the next `start` re-imports fixtures):

```bash
npm run translate:refresh
```

**WP CLI:**

```bash
npm run translate:env -- run cli -- wp <command>
```

**Run tests** (the Translation Events plugin's PHPUnit suite, in a dedicated test environment):

```bash
npm run translate:test
```

**Local overrides:** create `translate/.wp-env.override.json` (git-ignored) to override config values like `WP_HOME` / `WP_SITEURL` for testing behind a custom hostname.

**Translation Events 2024 design:** the events routes render the legacy templates unless the new block theme is enabled. To preview it, add `"config": { "TRANSLATION_EVENTS_NEW_DESIGN": true }` to `translate/.wp-env.override.json` and restart.

### Support Forums

A local instance of the WordPress.org Support Forums with bbPress, the `wporg-support-2024` theme, and the `wporg-bbp-*` supporting plugins. It runs as a multisite network, because the forums read the plugin and theme directories out of sibling sub-sites.

**Start:**

```bash
npm run support:env start
```

**Access:** `http://localhost:8888`

**Re-seed** (clears the seed flag, then re-runs the seed):

```bash
npm run support:refresh
```

**WP CLI:**

```bash
npm run support:env -- run cli -- wp <command>
```

Add `--url=localhost:8888/rosetta` (or `/plugins`, `/themes`) to target a sub-site.

**The network:**

| Blog | Path | Purpose |
|---|---|---|
| 1 | `/` | The global support forums — the site under development |
| 2 | `/plugins` | Plugin Directory, a dependency of the `/plugin/<slug>/` forum views |
| 3 | `/themes` | Theme Directory, a dependency of the `/theme/<slug>/` forum views |
| 4 | `/rosetta` | A locale ("Rosetta") forum, for the locale-only code paths |

The blog IDs are pinned in `.wp-env.json` (`WPORG_PLUGIN_DIRECTORY_BLOGID` and friends), so the seed creates the sub-sites in that order and fails loudly if they come out differently.

**Note that the plugin and theme directories here exist only as forum dependencies. Use the Plugin Directory and Theme Directory environments to work on the directories themselves.**

On production each locale forum is its own network with `IS_ROSETTA_NETWORK` defined, which a single `wp-config.php` cannot express. `mocks/mu-plugins/wporg-rosetta-network.php` defines it for the blog named by `WPORG_LOCAL_ROSETTA_BLOGID` instead.

**Forum IDs:** the `Plugins`, `Themes` and `Reviews` forums are created as the post IDs that `Plugin::PLUGINS_FORUM_ID` and `Support_Compat::HIDDEN_FORUMS` hard-code for production (21261, 21262, 21272, plus two legacy IDs). The directory compat views, the review forum, and the hidden-forum filtering all key off those, so they cannot be left to auto-increment.

**User accounts:**

All accounts use the password `password`.

| Username | Role on `/` | Role on `/rosetta` | Notes |
|---|---|---|---|
| `admin` | Network administrator | Network administrator | Full network admin access |
| `keymaster` | `bbp_keymaster` | `bbp_participant` | Top-level forum admin; can manage all forum content |
| `moderator` | `bbp_moderator` | `bbp_participant` | Can moderate topics and replies |
| `rosettakeymaster` | `bbp_participant` | `bbp_keymaster` | Keymaster of the locale forum |
| `rosettamoderator` | `bbp_participant` | `bbp_moderator` | Moderator of the locale forum |
| `pluginauthor` | `bbp_participant` | `bbp_participant` | Committer on the seeded Hello Dolly plugin |
| `plugincontributor` | `bbp_participant` | `bbp_participant` | Contributor on the seeded Hello Dolly plugin |
| `pluginsupport` | `bbp_participant` | `bbp_participant` | Support rep on the seeded Hello Dolly plugin |
| `themeauthor` | `bbp_participant` | `bbp_participant` | Author of the seeded Twenty Twenty-Four theme |
| `themesupport` | `bbp_participant` | `bbp_participant` | Theme support rep; no production equivalent yet |
| `visitor` | `bbp_participant` | `bbp_participant` | Regular forum visitor |

**Block editor:** the forums use the block editor through [Blocks Everywhere](https://github.com/Automattic/blocks-everywhere), which is not installed here — it needs a Gutenberg old enough to break against WordPress trunk until [Automattic/blocks-everywhere#211](https://github.com/Automattic/blocks-everywhere/pull/211) lands. `Plugin::__construct()` loads the block support only when that plugin is present, so the environment runs on bbPress' plain editor until then.

### WordPress.org SSO

A test-only environment for the shared single sign-on code in `common/includes/wporg-sso/`. The SSO is a library rather than a plugin, so it is mounted at `wp-content/wporg-sso` instead of being activated, and its PHPUnit suite runs from there.

`WP_ENVIRONMENT_TYPE` is set to `production` so the SSO uses the hosts it uses in production (`login.wordpress.org` and friends) rather than the shortcuts it takes on local installs.

**Run tests:**

```bash
npm run sso:test
```

### Handbook (in-plugin)

The Handbook plugin has its own `.wp-env.json` in `wordpress.org/public_html/wp-content/plugins/handbook/`.

**Start:**

```bash
cd wordpress.org/public_html/wp-content/plugins/handbook
npx wp-env start
```

**Run tests:** use the test environment in this directory instead — it starts a dedicated instance and runs the suite in one step:

```bash
npm run handbook:test
```

## Common Commands

```bash
# Stop an environment (replace plugins with jobs, etc.)
npm run plugins:env stop
npm run jobs:env stop

# Destroy an environment (removes all data)
npm run plugins:env destroy
npm run jobs:env destroy

# View logs
npm run plugins:env logs
npm run jobs:env logs
```

All commands should be run from the `environments/` directory unless otherwise noted.
