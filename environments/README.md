# WordPress.org Local Development Environments

Local development environments for WordPress.org projects, powered by [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

## Prerequisites

- [Docker](https://www.docker.com/products/docker-desktop/) installed and running
- [Node.js](https://nodejs.org/) >= 20

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

First start auto-imports `hello-dolly` (plugin) and `twentytwenty` (theme) so the `WordPress Plugins` and `WordPress Themes` project containers have real fixtures.

**Access:** `http://localhost:8888`

**Import a plugin or theme's translations on demand:**

```bash
npm run translate:import -- plugin akismet
npm run translate:import -- theme twentytwentyfour
```

**Re-seed** (clears the seed flag so the next `start` re-imports fixtures):

```bash
npm run translate:refresh
```

**WP CLI:**

```bash
npm run translate:env -- run cli -- wp <command>
```

**Local overrides:** create `translate/.wp-env.override.json` (git-ignored) to override config values like `WP_HOME` / `WP_SITEURL` for testing behind a custom hostname.

### Support Forums

A local instance of the WordPress.org Support Forums with bbPress, the support theme, and supporting plugins.

**Start:**

```bash
npm run support:env start
```

First start automatically sets up a multisite network, creates sub-sites for Plugins (`/plugins`) and Themes (`/themes`), and provisions the default user accounts.

**Note that these plugin and theme directories are only used for forum references, not for development of the respective environments.**

**Access:** `http://localhost:8888`

**WP CLI:**

```bash
npm run support:env -- run cli -- wp <command>
```

**User accounts:**

All accounts use the password `password`.

| Username | Forum role | Notes |
|---|---|---|
| `admin` | Network administrator | Full network admin access |
| `keymaster` | `bbp_keymaster` | Top-level forum admin; can manage all forum content |
| `moderator` | `bbp_moderator` | Can moderate topics and replies |
| `pluginauthor` | `bbp_participant` | Subscriber on the Plugins sub-site |
| `plugincontributor` | `bbp_participant` | Subscriber on the Plugins sub-site |
| `pluginsupport` | `bbp_participant` | Plugin support rep; subscriber on the Plugins sub-site |
| `themeauthor` | `bbp_participant` | Subscriber on the Themes sub-site |
| `themesupport` | `bbp_participant` | Theme support rep; subscriber on the Themes sub-site (unused) |
| `visitor` | `bbp_participant` | Regular site visitor |

### Handbook (in-plugin)

The Handbook plugin has its own `.wp-env.json` in `wordpress.org/public_html/wp-content/plugins/handbook/`.

**Start:**

```bash
cd wordpress.org/public_html/wp-content/plugins/handbook
npx wp-env start
```

**Run tests:**

```bash
npx wp-env run phpunit phpunit -c /var/www/html/wp-content/plugins/handbook/phpunit.xml
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
