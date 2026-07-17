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

### 2. Community Events (`public_html/events/`)
- Serves localized WordPress Meetups and WordCamps to the dashboard widget in WordPress installations based on location/IP.
- Handler script: `1.0/index.php`. Contains PHPUnit tests under `1.0/tests/`.

### 3. Themes and Patterns Directories (`public_html/themes/` & `public_html/patterns/`)
- **[themes/info](public_html/themes/info)**: Theme query and detail API (handles actions like `theme_information`, `query_themes`, `feature_list`).
- **[patterns/1.0](public_html/patterns/1.0)**: Block patterns registry lookup API.

### 4. Translation Packages (`public_html/translations/`)
- Manages translation updates for core, plugins, and themes.
- Uses `translations/lib.php` to query translation packages registered in the `language_packs` table.

---

## Technical Details

- **Bootstrapping:** Most API files load a minimal WordPress environment via `init.php` rather than a full page load. A couple of endpoints (e.g. `packages/p2/index.php`, `themes/info/1.0/index.php`) instead load `wp-init-ondemand.php`, which lives in the private dotorg repository and so isn't present in this one.
- **Caching:** Endpoints that read a specific directory's cached data must first point the object cache at that blog: `$wp_object_cache->blog_prefix = WPORG_PLUGIN_DIRECTORY_BLOGID` (or `…THEME…`).
