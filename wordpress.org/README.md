# wordpress.org Subproject Codebase

This subdirectory contains the custom theme files, plugins, and mu-plugins that power the **WordPress.org** multisite network, including directories, forums, support databases, localized Rosetta portals, and educational materials.

---

## Plugin Directory (`public_html/wp-content/plugins/`)

Here is an overview of the key custom plugins in this subproject:

### 1. The Core Directories
- **[plugin-directory/](public_html/wp-content/plugins/plugin-directory)**: Powers the plugin registry. Controls zip file validation, SVN repository syncs, readme parsing, search integration, trademarks, template views, and CLI operations.
- **[theme-directory/](public_html/wp-content/plugins/theme-directory)**: Powers the theme registry. Controls theme uploads, Theme Check integration, packages parsing, REST API routes, and theme query modifications.
- **[photo-directory/](public_html/wp-content/plugins/photo-directory)**: Powers the photos registry. Leverages Google Cloud Storage for assets, Google Vision API for content safety, color extraction utility, moderation queues, tags, favorites, and user badges.

### 2. Support & Help Documentation
- **[support-forums/](public_html/wp-content/plugins/support-forums)**: Custom extension of bbPress that manages the support forums. Handles moderators notes, audit logging, ratings, spam mitigation, nsfw detection, and user notes.
- **[support-helphub/](public_html/wp-content/plugins/support-helphub)**: Powers the HelpHub documentation portal. Manages custom documentation roles, page layout blocks, read time, and front page layouts.
- **[handbook/](public_html/wp-content/plugins/handbook)**: Manages developer/contributor handbooks with automated table of contents, breadcrumbs, watches, changes notifications, and walker structures.

### 3. Localization & Translation (Rosetta & GlotPress)
- **[rosetta/](public_html/wp-content/plugins/rosetta)**: Manages localized subdomains (e.g. `es.wordpress.org`). Handles locale main redirects, translation teams site creation, database lookups, and roles.
- **GlotPress Extensions (`wporg-gp-*`)**: Over a dozen plugins that configure and optimize GlotPress translations, including discussions, slack alerts, translations suggestion APIs, stats widgets, and pretranslation builders.

### 4. Learning & Events
- **[wporg-learn/](public_html/wp-content/plugins/wporg-learn)**: Powers `learn.wordpress.org`. Integrates with Sensei LMS, manages lesson content, custom courses taxonomy, form routes, and Markdown import helpers.
- **[official-wordpress-events/](public_html/wp-content/plugins/official-wordpress-events)**: Widgets and API helpers querying official WordCamps and Meetups.

---

## Must-Use Plugins (`public_html/wp-content/mu-plugins/pub/`)

The [mu-plugins/pub/](public_html/wp-content/mu-plugins/pub) folder is filled with essential global utilities:

- `wporg-redirects.php`: Manages meta site redirect routing rules.
- `wporg-robots.php`: Handles dynamic robots.txt rules.
- `wporg-seo.php` & `wporg-seo/`: SEO tags, sitemaps, and indexing optimizations.
- `site-branding.php`: Standardizes styling headers/footers for WordPress.org sites.
- `locale-switcher/` & `locales.php`: Swapping multi-locale subdomains.
- `servehappy-config.php`: PHP environment advisor configs.

---

## Themes (`public_html/wp-content/themes/pub/`)

WordPress.org uses distinct, block-based or classic child themes for its sub-sites, located in [themes/pub/](public_html/wp-content/themes/pub):

- `wporg-main`: The theme for the main wordpress.org site.
- `wporg-login`: Styles and structures for the unified login portal.
- `wporg-plugins-2024`: Block theme powering the Plugins Directory.
- `wporg-learn-2024`: Block theme powering learn.wordpress.org.
- `wporg-support-2024`: Block theme powering the Forums and HelpHub.
- `wporg-showcase`: Theme for the site showcase gallery.
- `wporg-openverse`: Integration layout for Openverse.
- `wporg-breathe-2024`: Block theme skin.
