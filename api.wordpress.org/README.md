# api.wordpress.org API Endpoint codebase

This subdirectory contains the API handlers serving requests for `api.wordpress.org`. It is the central nervous system for WordPress installations globally to check for updates, query community events, search theme/plugin information, and access localized translation updates.

---

## Directory Overview

The code is organized under `public_html/`:

### 1. Core Services (`public_html/core/`)
- **[browse-happy](public_html/core/browse-happy)**: Checks browser User-Agents to determine if updates or security notices are required. Powering the WordPress admin dashboard browser nag.
- **[serve-happy](public_html/core/serve-happy)**: Serves PHP version compatibility recommendations, encouraging users to upgrade outdated PHP environments.
- **[credits](public_html/core/credits)**: WordPress credits list API.
- **[importers](public_html/core/importers)**: Registered data importers configuration API.

### 2. Composer Metadata Registry (`public_html/packages/p2/`)
Provides a Composer-compatible v2 package repository for WordPress plugins and themes.
- Endpoint pattern: `/packages/p2/wp-{type}/{slug}.json` (where type is plugin, theme, or core).
- **Core Files:**
  - `class-composer-repository.php`: Handles request routing, caching, and serialization.
  - `class-package-builder.php`: Assembles Composer package definitions from the plugin/theme directory databases.
  - `class-version-normalizer.php`: Standardizes package version strings.

### 3. Community Events (`public_html/events/`)
- Serves localized WordPress Meetups and WordCamps to the dashboard widget in WordPress installations based on location/IP.
- Handler script: `1.0/index.php`. Contains PHPUnit tests under `1.0/tests/`.

### 4. Themes and Patterns Directories (`public_html/themes/` & `public_html/patterns/`)
- **[themes/info](public_html/themes/info)**: Theme query and detail API (handles actions like `theme_information`, `query_themes`, `feature_list`).
- **[patterns/1.0](public_html/patterns/1.0)**: Block patterns registry lookup API.

### 5. Translation Packages (`public_html/translations/`)
- Manages translation updates for core, plugins, and themes.
- Uses `translations/lib.php` to query translation packages registered in the `language_packs` table.

---

## Technical Details

- **Bootstrapping:** Most API files load a dynamically deployed, untracked helper file `wp-init-ondemand.php` to bootstrap a minimal WordPress environment without incurring the resource load of a full page load.
- **Caching:** The API caches query results globally in Memcached using consistent prefixes like `blog_prefix = WPORG_PLUGIN_DIRECTORY_BLOGID` or `WPORG_THEME_DIRECTORY_BLOGID`.
