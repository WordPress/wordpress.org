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

### Post Translation

A local instance for the Post Translation plugin with the GlotPress Translate Bridge dependency.

**Start:**

```bash
npm run post-translation:env start
```

**Run tests:**

```bash
npm run post-translation:env -- run cli --env-cwd=wp-content/plugins/wporg-post-translation phpunit
```

**Access:** `http://localhost:8888`

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
