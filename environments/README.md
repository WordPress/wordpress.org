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
npm run plugins:env
```

**What it includes:**

- Plugin Directory plugin (from the repository)
- Plugin Check, Jetpack, and GlotPress plugins
- wporg-plugins-2024 theme with wporg-parent-2021
- wporg-mu-plugins (from the `WordPress/wporg-mu-plugins` build branch)
- Mock mu-plugins for production-only dependencies (query filter, mu-plugins loader)
- Auto-imports ~30 plugins from WordPress.org on first start (featured, popular, beta)

**Re-import plugins:**

```bash
npm run plugins:refresh
```

**Access:** `http://localhost:8888`

**WP CLI:**

```bash
npx wp-env run cli wp <command>
```

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
# Stop the environment
npx wp-env stop

# Destroy the environment (removes all data)
npx wp-env destroy

# View logs
npx wp-env logs
```

All commands should be run from the `environments/` directory unless otherwise noted.
