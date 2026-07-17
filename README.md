# WordPress Meta Git Repository

Welcome to the **WordPress Meta Git Repository**. This repository is an official mirror of the canonical Subversion (SVN) repository for WordPress.org meta services. It contains the source code, themes, plugins, configuration, and tools powering the various backend systems, network-wide multisites, APIs, and community tools of the **WordPress.org** ecosystem.

---

## Workspace Map

Here is a comprehensive directory map of the active services at the root of this Git repository:

| Subproject / Service | Description | Documentation |
| :--- | :--- | :--- |
| 🔌 **[api.wordpress.org](api.wordpress.org)** | Host for the WordPress core API endpoints (updates, version suggestions, community events, translations, Composer v2 package repository). | 📄 [API Documentation](api.wordpress.org/README.md) |
| 🌐 **[wordpress.org](wordpress.org)** | Codebase powering primary WordPress.org hubs. Includes custom themes, plugins, and mu-plugins (Plugin Directory, Theme Directory, Support Forums, Rosetta, HelpHub, Photo Directory, learn.wordpress.org). | 📄 [WordPress.org Documentation](wordpress.org/README.md) |
| 📦 **[common](common)** | Shared library, modules, and utilities across sites (SSO client, Slack hooks, profiles, and testing structure). | 📄 [Common Shared Docs](common/README.md) |
| 💻 **[environments](environments)** | Local development environment configurations powered by Docker and `wp-env`. | 📄 [Environments Guide](environments/README.md) |
| ⚙️ **[.github](.github)** | Custom CI workflows (Static analysis branch checks, Docker wp-env unit tests, Live API monitors, and Props bots) and helper scripts. | 📄 [GitHub Actions Docs](.github/README.md) |
| 🗣️ **[browsehappy.com](browsehappy.com)** | Theme and settings for browsehappy.com, checking latest web browser versions to promote up-to-date web usage. | 📄 [Browse Happy Readme](browsehappy.com/public_html/README.md) |
| 👥 **[buddypress.org](buddypress.org)** | Themes, plugins, and codices powering the bbPress.org and BuddyPress.org project sites. | — |
| 💼 **[jobs.wordpress.net](jobs.wordpress.net)** | Directory plugins and themes powering the official WordPress job board (JobsWP plugin). | — |
| 👤 **[profiles.wordpress.org](profiles.wordpress.org)** | Custom handlers for user profiles activity, profiles association, and profile management. | — |
| 📥 **[svn.wordpress.org](svn.wordpress.org)** | Slack integration hooks triggered by Subversion commits or Trac tickets. | — |
| 🎫 **[trac.wordpress.org](trac.wordpress.org)** | Trac environment configurations, SQLite/MySQL migration utilities, and custom workflow configuration `.ini` files. | — |
| 🖼️ **[wp-themes.com](wp-themes.com)** | Custom preview handlers, block styles variations, and comment/xmlrpc disallowing plugins for the live theme preview system. | — |
| 🍰 **[wp15.wordpress.net](wp15.wordpress.net)** | Archive site commemorating the WordPress 15th anniversary. | — |
| 🌎 **[global.wordpress.org](global.wordpress.org)** | Subdomain configurations for global networks. | — |
| 🎬 **[wordpress.tv](wordpress.tv)** | Video collection site themes (`wptv2` and `wptvblog2`). | — |
| ⚡ **[doaction.org](doaction.org)** | Placeholder for doaction charity hackathons (currently empty). | — |

---

## Quick Start Local Development

For most subprojects, you can spin up a local development instance with Node.js and Docker via `wp-env` under the `environments/` directory:

1. Ensure **Docker Desktop** is running.
2. Navigate to [environments/](environments).
3. Run `npm install` to install dependencies.
4. Launch the environment of your choice:
   - Plugin Directory: `npm run plugins:env start`
   - Theme Directory: `npm run themes:env start`
   - Jobs: `npm run jobs:env start`
   - Browse Happy: `npm run browsehappy:env start`
   - Translate (GlotPress): `npm run translate:env start`

For more detailed information, see the [Environments Guide](environments/README.md).

---

## Contribution & PR Workflow

Because this repository is a read-only mirror of the `meta.svn.wordpress.org` Subversion (SVN) codebase:

1. **Link the ticket you're fixing:** If your PR addresses an existing [WordPress.org Meta Trac](https://meta.trac.wordpress.org/) ticket, always reference it in the PR description (e.g., `https://meta.trac.wordpress.org/ticket/XXXXX` or `Meta Trac #XXXXX`).
2. **Commit Process:** Staged Git PR changes will not be merged directly on GitHub; a Meta committer will review and commit the patch to the Meta repository, which will automatically sync and resolve the PR on GitHub.
