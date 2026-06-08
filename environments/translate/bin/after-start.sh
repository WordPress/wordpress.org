#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, activates plugins, and seeds GlotPress fixtures.
#

CONFIG="--config translate/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"
WPENV="npx wp-env $CONFIG run"

# Install CLI tools needed for downloading/extracting plugin & theme zips.
echo "Installing CLI tools..."
$WPENV wordpress sudo bash -c \
	'command -v unzip > /dev/null || (apt-get -qy update && apt-get -qy install subversion unzip zip) > /dev/null 2>&1'
$WPENV cli sudo sh -c \
	'command -v unzip > /dev/null || apk add --no-cache -q subversion unzip zip coreutils > /dev/null 2>&1'

# Reset active_plugins BEFORE any other wp command — wp-env's initial
# activation order can leave wporg-gp-* loading before glotpress, which
# blows up plugins_loaded callbacks that reference gp_startswith().
$WP wp option update active_plugins '[]' --format=json
$WP wp plugin activate glotpress gp-translation-helpers gp-import-export

# GlotPress requires pretty permalinks.
$WP wp rewrite structure '/%postname%/' --hard

# Match production site identity.
$WP wp option update blogname 'Translating WordPress'
$WP wp option update blogdescription 'Contribute to WordPress core, themes, and plugins by translating them into your language'

# Activate theme. wp-env flattens the source path so 'pub/wporg' is mounted as 'wporg'.
$WP wp theme activate wporg

# Activate the remaining plugins now that GlotPress is loaded.
echo "Activating plugins..."
$WP wp plugin activate --all

# GlotPress only runs its schema upgrade when is_admin() is true (gp-settings.php),
# so wp plugin activate via CLI does not create the gp_* tables. Trigger it explicitly.
echo "Ensuring GlotPress schema is up to date..."
$WP wp eval '
	require_once ABSPATH . "wp-admin/includes/upgrade.php";
	require_once GP_PATH . GP_INC . "install-upgrade.php";
	require_once GP_PATH . GP_INC . "schema.php";
	gp_upgrade_db();
'

# wporg-gp-custom-stats reads from extra tables (user_translations_count, etc.)
# that production maintains manually — the plugin does not create them.
echo "Creating wporg-gp-custom-stats tables..."
$WP wp db import wp-content/env-bin/extra-tables.sql

# Seed the GlotPress project tree to match production layout.
# - wporg-gp-routes/inc/routes/class-index.php hard-codes project_id=2 as the
#   "wp/dev" project when listing locales on the homepage, so wp must be id 1
#   and wp/dev must be id 2.
# - wporg-gp-routes/inc/routes/class-stats.php expects projects at paths
#   patterns/core, meta/*, apps/*, waiting, wp-plugins, wp-themes for /stats/.
# Idempotent — skip if wp/dev already exists.
echo "Seeding GlotPress project tree..."
$WP wp eval '
	if ( GP::$project->by_path( "wp/dev" ) ) {
		echo "  exists wp/dev, skipping project seed\n";
		return;
	}

	$wpdb = $GLOBALS["wpdb"];
	$wpdb->query( "SET FOREIGN_KEY_CHECKS=0" );
	foreach ( array( "gp_translations", "gp_translation_sets", "gp_originals", "gp_projects", "gp_permissions" ) as $t ) {
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$t}" );
	}
	$wpdb->query( "SET FOREIGN_KEY_CHECKS=1" );

	$mk = function( $name, $slug, $parent_id = null ) {
		// active=1 is required — the /locale/* SQL filters out inactive projects
		// and GP::$project->create() does NOT set it by default.
		return GP::$project->create( array( "name" => $name, "slug" => $slug, "parent_project_id" => $parent_id, "active" => 1 ) );
	};

	$wp       = $mk( "WordPress",         "wp"         );  // id 1
	$dev      = $mk( "Development",       "dev",       $wp->id ); // id 2
	$plugins  = $mk( "WordPress Plugins", "wp-plugins" );
	$themes   = $mk( "WordPress Themes",  "wp-themes"  );

	$patterns = $mk( "Patterns", "patterns" );
	$mk( "Core", "core", $patterns->id );

	$meta = $mk( "Meta", "meta" );
	foreach ( array(
		"wordpress-org" => "WordPress.org",
		"rosetta"       => "Rosetta",
		"browsehappy"   => "Browsehappy",
		"themes"        => "Themes",
		"plugins-v3"    => "Plugins v3",
		"forums"        => "Forums",
	) as $s => $n ) {
		$mk( $n, $s, $meta->id );
	}

	$apps = $mk( "Apps", "apps" );
	$mk( "Android", "android", $apps->id );
	$mk( "iOS",     "ios",     $apps->id );

	$mk( "Waiting", "waiting" );

	// Seed a handful of locales as empty translation sets on wp/dev so the
	// homepage locale grid renders something.
	foreach ( array( "en-gb","fr","de","es","ja","ru","zh-cn","pt-br","it","nl","pl","sv" ) as $locale ) {
		GP::$translation_set->create( array(
			"name"       => $locale,
			"slug"       => "default",
			"project_id" => $dev->id,
			"locale"     => $locale,
		) );
	}

	wp_cache_flush();
	echo "  seeded wp/dev (id={$dev->id}), wp-plugins (id={$plugins->id}), wp-themes (id={$themes->id}), 12 locales\n";
'

# Grant the admin user GP admin + the wporg-115 capability that
# wporg-gp-rosetta-roles::is_global_administrator() looks for, so the dev
# user can approve translations across every project and locale.
echo "Granting admin GlotPress permissions..."
$WP wp eval '
	$admin = get_user_by( "login", "admin" );
	if ( ! $admin ) { return; }
	update_user_meta( $admin->ID, "wporg_115_capabilities", array( "administrator" => true ) );
	if ( ! GP::$permission->find_one( array( "user_id" => $admin->ID, "action" => "admin" ) ) ) {
		GP::$permission->create( array( "user_id" => $admin->ID, "action" => "admin" ) );
	}
'

# Auto-seed real fixtures on first start.
if [ -z "$($WP wp option get wporg_translate_env_seeded 2>/dev/null)" ]; then
	echo "Seeding hello-dolly (plugin)..."
	$WP wp eval-file wp-content/env-bin/import-from-wporg.php plugin hello-dolly

	echo "Seeding twentytwenty (theme)..."
	$WP wp eval-file wp-content/env-bin/import-from-wporg.php theme twentytwenty

	$WP wp option update wporg_translate_env_seeded "$(date +%s)"
else
	echo "Seed fixtures already imported, skipping."
fi

# Rebuild wporg-gp-custom-stats tables so /stats/ shows numbers. Production
# updates these incrementally via the gp_translation_saved hook; bulk imports
# never go through that hook so we recompute from scratch.
echo "Rebuilding project translation stats..."
$WP wp eval '
	do_action( "gp_init" );
	global $wporg_gp_custom_stats, $wpdb;
	if ( ! isset( $wporg_gp_custom_stats ) ) { return; }
	$proj = $wporg_gp_custom_stats->project;
	$sets = $wpdb->get_results( "SELECT id, project_id, locale, slug FROM {$wpdb->prefix}gp_translation_sets" );
	foreach ( $sets as $s ) {
		// get_project_translation_counts() returns ["all"=>N, "current"=>N, "waiting"=>N, ...]
		// — same shape production reads in WPorg_GP_Project_Stats::shutdown(). The earlier
		// (array) reset($counts) pattern unpacked the FIRST value as a 1-element array, so
		// every count ended up as 0 and Plugin::get_translation_status() then fed empty rows
		// into index-locales.php where current_count / all_count throws DivisionByZeroError.
		$counts = $proj->get_project_translation_counts( $s->project_id, $s->locale, $s->slug );
		if ( 0 === (int) $counts["all"] ) { continue; }
		// has_pending mirrors production (denormalized "waiting > 0 OR fuzzy > 0").
		// wporg-gp-routes/class-locale "needs attention" queries scan on it.
		$has_pending = ( $counts["waiting"] > 0 || $counts["fuzzy"] > 0 ) ? 1 : 0;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}gp_project_translation_status
			 (project_id, locale, locale_slug, `all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated`, has_pending, date_added, date_modified)
			 VALUES (%d, %s, %s, %d, %d, %d, %d, %d, %d, %d, NOW(), NOW())
			 ON DUPLICATE KEY UPDATE
			 `all`=VALUES(`all`), `current`=VALUES(`current`), `waiting`=VALUES(`waiting`),
			 `fuzzy`=VALUES(`fuzzy`), `warnings`=VALUES(`warnings`), `untranslated`=VALUES(`untranslated`),
			 has_pending=VALUES(has_pending), date_modified=NOW()",
			$s->project_id, $s->locale, $s->slug,
			(int) $counts["all"], (int) $counts["current"], (int) $counts["waiting"],
			(int) $counts["fuzzy"], (int) $counts["warnings"], (int) $counts["untranslated"],
			$has_pending
		) );
	}
	$proj->cache_wp_themes_wp_plugins_strings();
'

echo "Done."
