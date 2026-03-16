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

### Theme Directory

A local instance of the WordPress.org Theme Directory with the theme directory plugin, theme, and supporting mu-plugins.

**Start:**

```bash
npm run themes:env start
```

**Re-seed themes** (clears import flag, then re-imports):

```bash
npm run themes:refresh
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
# Stop the environment
npm run plugins:env stop

# Destroy the environment (removes all data)
npm run plugins:env destroy

# View logs
npm run plugins:env logs
```

All commands should be run from the `environments/` directory unless otherwise noted.
