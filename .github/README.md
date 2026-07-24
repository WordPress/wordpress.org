# GitHub Configuration, Actions & Workflows

This folder contains the repository's configuration for GitHub, automated CI/CD workflows, and custom helper scripts for static analysis.

---

## Directory Map

- **[workflows/](workflows/)**: Automated GitHub Actions yaml files.
- **[bin/](bin/)**: Supporting scripts used by workflows or developers.

---

## Custom Scripts (`.github/bin/`)

### 1. [phpcs-branch.php](bin/phpcs-branch.php)
- **Purpose:** Optimizes static analysis checks inside PRs. Instead of checking the whole repository (which contains legacy code violations), it isolates PHPCS violations to **only the lines added or modified in the current branch**.
- **Mechanism:**
  - Runs standard `phpcs` on newly added files (`diff-filter=A`).
  - Runs `phpcs-changed` on modified files (`diff-filter=M`), creating temporary files/diffs to calculate only the errors introduced on the changed lines.
- **Usage:**
  ```bash
  BASE_REF=trunk php .github/bin/phpcs-branch.php
  ```

---

## GitHub Actions Workflows (`.github/workflows/`)

### 1. [Static Analysis (PHP Linting)](workflows/phpcs.yml) (`phpcs.yml`)
- **Trigger:** Runs on pull requests that modify PHP files, `phpcs.xml.dist`, `composer.json`, or the workflow files themselves.
- **Actions:** Sets up PHP 8.4, installs composer tools, and executes the `phpcs-branch.php` helper script to report PHPCS syntax/standard compliance on PR diffs.

### 2. [Unit Tests](workflows/unit-tests.yml) (`unit-tests.yml`)
- **Trigger:** Runs on pull requests and pushes to `trunk`.
- **Jobs:**
  - **`php-standalone`**: Runs PHPUnit on modules that do not depend on WordPress or a database (Serve Happy, Browse Happy, Events API, Slack Trac Bot, and Slack Props Library).
  - **`php-wordpress`**: Sets up Node.js, installs Docker-based `@wordpress/env` (`wp-env`), spins up the local container environment, injects PHPUnit polyfills, and runs unit tests for the Handbook, Plugin Directory, and Theme Directory plugins.

### 3. [Events API (live) Checks](workflows/events-api-live.yml) (`events-api-live.yml`)
- **Trigger:** Runs daily at 14:00 UTC and on manual workflow dispatch.
- **Purpose:** Executes integration/E2E and data freshness checks against the live production endpoints (`api.wordpress.org` and `wordcamp.org`). Runs in isolation from the PR checks to avoid blocking PR merges due to transient external/live issues.

### 4. [Props Bot](workflows/props-bot.yml) (`props-bot.yml`)
- **Trigger:** Pull request lifecycle events (opened, reopened, ready for review, labeled with `props-bot`, or commented on).
- **Purpose:** Automatically scans the pull request's author, commit messages, and review comments to compile a contributor "Props" credit line formatted in WordPress SVN style. Comments the list of credits back on the PR.
