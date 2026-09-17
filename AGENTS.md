# AGENTS.md — Agent & Developer Context Guide

This document provides specialized architectural context, guidelines, and conventions for **AI coding agents** and developers working in this repository. Use this to quickly orient yourself before planning edits or proposing enhancements.

---

## Workspace Architecture

This is a Git repository containing a mirror of the WordPress.org Meta environment.

- **Primary Working Directory:** The repository root directory. All subprojects (such as `api.wordpress.org`, `wordpress.org`, etc.) are located directly at the root.
- **PHP Compatibility:** The codebase aims to be compatible with PHP 8.4 and runs on WordPress `trunk` (the in-development version).
- **GitHub Tooling:** Configuration and CI workflow files are located in [.github/](.github) (see detailed guides in [.github/README.md](.github/README.md)).

---

## Architectural Patterns & Conventions

### 1. The On-Demand Bootstrapping Pattern
Many API endpoints (e.g., in `api.wordpress.org`) require access to core WordPress functions but run outside of a standard WordPress web root. They bootstrap WordPress dynamically using:
```php
require_once dirname( ... ) . '/wp-init-ondemand.php';
```
> [!IMPORTANT]
> The file `wp-init-ondemand.php` lives in the **private dotorg repository**, so it is **not present in this repository**. Agents should not attempt to locate it or modify its call.

### 2. Multi-site and Object Cache Prefixing
To load or cache data from a specific multisite blog (like the plugin directory or theme directory), code points the object cache at that blog by overriding its per-blog key prefix:
```php
$wp_object_cache->blog_prefix = WPORG_THEME_DIRECTORY_BLOGID;
```
Always preserve these assignments when handling low-level database queries or cache actions in the APIs.

### 3. Shared Single Sign-On (SSO)
The repository implements a shared SSO mechanism under [common/includes/wporg-sso/](common/includes/wporg-sso) that coordinates authentication between the main WordPress.org sites, bbPress forums, and GlotPress translations.

---

## Security Trust Model

Roles on WordPress.org multisite blogs are assigned individually rather than granted on sign-up. Much of the estate leans on this: capability checks are often coarse, because on most sites there is no untrusted principal below the people who run them.

This is not uniform — some sites register their own roles and appoint community members into them at scale — so confirm the trust model of the site you are working on before relying on it. The [HackerOne program policy](https://hackerone.com/wordpress) is the authority on which gaps count as in-scope vulnerabilities.

---

## Coding Standards & Linting

We enforce the **WordPress Coding Standards (WPCS)** with local adjustments configured in [phpcs.xml.dist](phpcs.xml.dist).

### Running Linters & Tests
Before making commits, you should execute:
- **Linting:** Run `composer run lint` at the root of the repository to run PHP_CodeSniffer.
- **Auto-Formatting:** Run `composer run format` to fix spacing/formatting violations automatically.
- **Unit Tests:** Individual plugins contain their own PHPUnit setups (e.g., `api.wordpress.org/public_html/events/1.0/tests/` and `wordpress.org/public_html/wp-content/plugins/plugin-directory/tests/`).

---

## Contribution & Pull Request Workflow

Because this Git repository is a read-only mirror of the `meta.svn.wordpress.org` Subversion (SVN) repository, developers and agents should follow this workflow for all modifications:

### 1. Meta Trac Ticket
*   **Link the ticket you're fixing:** If a PR addresses an existing [WordPress.org Meta Trac](https://meta.trac.wordpress.org/) ticket, always reference it in the PR description — link its URL (e.g., `https://meta.trac.wordpress.org/ticket/XXXXX`) or write `Meta Trac #XXXXX`.
*   **Commit Rights:** Code changes are not merged on GitHub directly. Instead, a Meta committer will review the PR, commit the patch to the Meta repository, and close the Trac ticket. SVN commits are automatically mirrored to GitHub, closing the PR in the process.

### 2. Contributor "Props" System
*   The repository utilizes an automated workflow called `Props Bot` (defined in [.github/workflows/props-bot.yml](.github/workflows/props-bot.yml)).
*   The bot automatically scans the commits, comments, and reviewers of the PR to compile a standard SVN-formatted "Props" attribution line (e.g., `Props username, corecommitter.`).
*   Ensure that any co-authors or helpers are correctly credited so they are recognized when the commit is written to SVN.

---

## Deep-Dive Integrations & Internals

### 1. GitHub PR to Trac Syncer (`api.wordpress.org/public_html/dotorg/trac/pr/`)
*   **Webhook Handler (`webhook.php`):** GitHub reports pull request events to this endpoint. The endpoint verifies the signature using `GH_PRBOT_WEBHOOK_SECRET`, fetches PR data, and records associations in the `trac_github_prs` database table.
*   **Ticket Detection (`functions.php`):** Implements `determine_trac_ticket()` to parse the PR body, title, or branch names using regex patterns. It supports formats like:
    - Explicit tag: `Trac ticket: https://{trac}.trac.wordpress.org/ticket/{id}`
    - Prefix: `Core-1234`, `Meta-5678`
    - Standard references: `#1234` (4+ digits), `Ticket 1234`
*   **Default Trac:** Assumes the `meta` Trac for the `WordPress/wordpress.org` repository, and `core` Trac for `WordPress/wordpress-develop`.

### 2. HelpScout Sidebar Applications (`api.wordpress.org/public_html/dotorg/helpscout/`)
*   Provides dynamic JSON endpoints integrated as custom sidebar apps in HelpScout.
*   Retrieves database information about directories, plugins, themes, and user registration dates to provide customer service teams with immediate context when answering support/review tickets.

### 3. "Gandalf" Security Scanner (`wordpress.org/public_html/wp-content/plugins/plugin-directory/jobs/`)
*   **Class `Plugin_Scan_Gandalf`:** Integrates with `https://gandalf.wordpress.org/scan` to automatically scan newly uploaded plugin zip archives.
*   Dispatches scan requests containing the zip download links, signed with `WP_GANDALF_SCAN_SHARED_SECRET`, and registers a REST callback API route (`plugins/v1/plugin/{post_name}/gandalf-scan`) to process verdicts and alert reviewers in Slack.

---

## Local Testing Setup

To test your changes, leverage the Docker/`wp-env` environments defined in [environments/](environments).

- Start command example: `npm run plugins:env start` inside the `environments` folder.
- Clear and import fresh seeds: `npm run plugins:refresh`.

For full instructions, read [environments/README.md](environments/README.md).
