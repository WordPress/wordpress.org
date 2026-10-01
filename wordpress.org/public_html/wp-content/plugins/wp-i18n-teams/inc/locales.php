<?php
/**
 * Shortcode for displaying details about translation teams.
 */

namespace WordPressdotorg\I18nTeams\Locales;

use GP_Locale;
use GP_Locales;
use WP_User;
use const WordPressdotorg\I18nTeams\PLUGIN_DIR;
use const WordPressdotorg\I18nTeams\PLUGIN_FILE;
use function WordPressdotorg\I18nTeams\get_locales;

/**
 * Inits the shortcode for displaying details about translation teams.
 */
function bootstrap(): void {
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 20 );
	add_shortcode( 'wp-locales', __NAMESPACE__ . '\render_wp_locales_shortcode' );
}

/**
 * Enqueues JavaScript and CSS.
 */
function enqueue_assets(): void {
	if ( is_singular() && false !== strpos( get_post()->post_content, '[wp-locales' ) ) {
		wp_enqueue_style( 'wp-i18n-teams', plugins_url( 'css/i18n-teams.css', PLUGIN_FILE ), [], filemtime( dirname( __DIR__ ) . '/css/i18n-teams.css' ) );
		wp_enqueue_script( 'wp-i18n-teams', plugins_url( 'js/i18n-teams.js', PLUGIN_FILE ), [ 'jquery', 'o2-app' ], filemtime( dirname( __DIR__ ) . '/js/i18n-teams.js' ) );
	}
}

/**
 * Render the [wp-locales] shortcode.
 */
function render_wp_locales_shortcode(): string {
	ob_start();

	if ( empty( $_GET['locale'] ) ) {
		$locales             = get_locales();
		$locale_data         = get_locales_data();
		$percentages         = get_core_translation_data();
		$language_packs_data = get_language_packs_data();
		include PLUGIN_DIR . '/views/all-locales.php';
	} else {
		require_once GLOTPRESS_LOCALES_PATH;
		$locale = GP_Locales::by_field( 'wp_locale', $_GET['locale'] );
		if ( $locale && 'en_US' !== $locale->wp_locale ) {
			$locale_data = get_extended_locale_data( $locale );
			include PLUGIN_DIR . '/views/locale-details.php';
		} else {
			status_header( 404 );
			printf(
				'<div class="callout callout-warning"><p>%s</p><p><a href="%s">%s</a></p></div>',
				sprintf(
					/* translators: %s: Locale slug. */
					esc_html__( 'Locale %s doesn&#8217;t exist.', 'wporg' ),
					'<code>' . esc_html( $_GET['locale'] ) . '</code>'
				),
				esc_url( get_permalink() ),
				esc_html__( 'Return to All Locales', 'wporg' )
			);
		}
	}

	return ob_get_clean();
}

/**
 * Retrieves all the required data and caches it.
 */
function get_locales_data() {
	global $wpdb;

	$cache = get_transient( 'wp_i18n_teams_locales_data' );
	if ( false !== $cache ) {
		return $cache;
	}

	$gp_locales          = get_locales();
	$translation_data    = get_core_translation_data();
	$language_packs_data = get_language_packs_data();
	$locale_data         = [];

	$statuses = [
		'no-wp-project'      => 0,
		'no-site'            => 0,
		'no-releases'        => 0,
		'latest'             => 0,
		'minor-behind'       => 0,
		'major-behind-one'   => 0,
		'major-behind-many'  => 0,
		'translated-100'     => 0,
		'translated-95'      => 0,
		'translated-90'      => 0,
		'translated-50'      => 0,
		'translated-50-less' => 0,
		'has-language-pack'  => 0,
		'no-language-pack'   => 0,
	];

	$wporg_data = $wpdb->get_results( 'SELECT locale, subdomain, latest_release FROM wporg_locales ORDER BY locale', OBJECT_K );

	foreach ( $gp_locales as $locale ) {
		$subdomain      = $wporg_data[ $locale->wp_locale ]->subdomain ?? '';
		$latest_release = $wporg_data[ $locale->wp_locale ]->latest_release ?? '';
		$release_status = get_locale_release_status( $subdomain, $latest_release );

		$statuses[ $release_status ]++;

		if ( isset( $translation_data[ $locale->wp_locale ] ) ) {
			$translation_status = get_locale_translation_status( $translation_data[ $locale->wp_locale ] );
		} else {
			$translation_status = 'no-wp-project';
		}
		$statuses[ $translation_status ]++;

		if ( isset( $language_packs_data[ $locale->wp_locale ] ) ) {
			$language_pack_status = 'has-language-pack';
		} else {
			$language_pack_status = 'no-language-pack';
		}
		$statuses[ $language_pack_status ]++;

		$sites = get_sites(
			[
				'locale'     => $locale->wp_locale,
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'orderby'    => 'path_length',
				'number'     => '',
			]
		);

		$locale_data[ $locale->wp_locale ] = [
			'release_status'       => $release_status,
			'translation_status'   => $translation_status,
			'language_pack_status' => $language_pack_status,
			'sites'                => $sites,
			'subdomain'            => $subdomain,
			'rosetta_site_url'     => $subdomain ? "https://$subdomain.wordpress.org/" : '',
			'latest_release'       => $latest_release ? $latest_release : false,
		];
	}

	$locale_data['status_counts']        = $statuses;
	$locale_data['status_counts']['all'] = \count( $gp_locales );
	set_transient( 'wp_i18n_teams_locales_data', $locale_data, 900 );
	return $locale_data;
}

/**
 * Retrieves all the language packs data and caches it.
 */
function get_language_packs_data() {
	global $wpdb;

	$cache = get_transient( 'wp_i18n_teams_language_packs_data' );
	if ( false !== $cache ) {
		return $cache;
	}

	$language_packs = $wpdb->get_results( "SELECT language AS locale, version FROM `language_packs` WHERE `type` = 'core' AND `active` = 1 AND `version` NOT LIKE '%-%'" );

	$language_packs_data = [];
	foreach ( $language_packs as $pack ) {
		if ( ! isset( $language_packs_data[ $pack->locale ] ) ) {
			$language_packs_data[ $pack->locale ] = [];
		}

		$language_packs_data[ $pack->locale ][] = $pack->version;
	}

	set_transient( 'wp_i18n_teams_language_packs_data', $language_packs_data, 900 );

	return $language_packs_data;
}

/**
 * Retrieves all the required data.
 */
function get_extended_locale_data( $locale ) {
	$locales_data = get_locales_data();

	$locale_data = $locales_data[ $locale->wp_locale ];

	$locale_data['localized_core_url'] = false;
	$locale_data['language_pack_url']  = false;

	$latest_release = $locale_data['latest_release'];
	if ( $latest_release ) {
		$locale_data['localized_core_url'] = sprintf( 'https://downloads.wordpress.org/release/%s/wordpress-%s.zip', $locale->wp_locale, $latest_release );
		$language_packs_data               = get_language_packs_data();

		if ( version_compare( $latest_release, '4.0', '>=' ) && ! empty( $language_packs_data[ $locale->wp_locale ] ) ) {
			list( $x, $y ) = explode( '.', $latest_release );
			$latest_branch = "$x.$y";

			$pack_version = null;
			if ( \in_array( $latest_release, $language_packs_data[ $locale->wp_locale ] ) ) {
				$pack_version = $latest_release;
			} elseif ( \in_array( $latest_branch, $language_packs_data[ $locale->wp_locale ] ) ) {
				$pack_version = $latest_branch;
			}

			if ( $pack_version ) {
				$locale_data['language_pack_version'] = $pack_version;
				$locale_data['language_pack_url']     = sprintf( 'https://downloads.wordpress.org/translation/core/%s/%s.zip', $pack_version, $locale->wp_locale );
			}
		}
	}

	$contributors                      = get_contributors( $locale );
	$locale_data['locale_managers']    = $contributors['locale_managers'];
	$locale_data['validators']         = $contributors['validators'];
	$locale_data['project_validators'] = $contributors['project_validators'];
	$locale_data['translators']        = $contributors['translators'];
	$locale_data['translators_past']   = $contributors['translators_past'];

	return $locale_data;
}

/**
 * Retrieves the translators and validators for the given locale.
 *
 * @return array
 */
function get_contributors( GP_Locale $locale ): array {
	$cache = wp_cache_get( 'contributors-data:' . $locale->wp_locale, 'wp-i18n-teams' );
	if ( false !== $cache ) {
		return $cache;
	}

	// Editors are only assigned to the parent locale.
	$target_locale = $locale;
	if ( ! empty( $locale->root_slug ) ) {
		$parent = GP_Locales::by_slug( $locale->root_slug );
		if ( $parent instanceof GP_Locale ) {
			$target_locale = $parent;
		}
	}

	$site_id = get_locale_site_id( $target_locale );

	$contributors                       = [];
	$contributors['locale_managers']    = $site_id ? get_users_by_role( $site_id, 'locale_manager' ) : [];
	$contributors['validators']         = $site_id ? get_users_by_role( $site_id, 'general_translation_editor' ) : [];
	$contributors['project_validators'] = $site_id ? get_users_by_role( $site_id, 'translation_editor' ) : [];

	$translators_data                   = get_translation_contributors( $locale, 365 );
	$contributors['translators']        = $translators_data['translators'];
	$contributors['translators_past']   = $translators_data['translators_past'];

	wp_cache_set( 'contributors-data:' . $locale->wp_locale, $contributors, 'wp-i18n-teams', 6 * HOUR_IN_SECONDS );

	return $contributors;
}

/**
 * Retrieves the core translations data and caches it.
 *
 * @return array
 */
function get_core_translation_data() {
	$cache = get_transient( 'core_translation_data' );
	if ( false !== $cache ) {
		return $cache;
	}

	$projects = [ 'wp/dev', 'wp/dev/cc', 'wp/dev/admin', 'wp/dev/admin/network' ];
	$counts   = $percentages = [];
	foreach ( $projects as $project ) {
		$results = json_decode( wp_remote_retrieve_body( wp_safe_remote_get( 'https://translate.wordpress.org/api/projects/' . $project ) ) );
		if ( ! $results ) {
			continue;
		}

		foreach ( $results->translation_sets as $set ) {

			if ( ! isset( $set->wp_locale ) ) {
				continue;
			}

			$wp_locale = $set->wp_locale;
			if ( 'default' !== $set->slug ) {
				$wp_locale = $wp_locale . '_' . $set->slug;
			}

			if ( ! isset( $counts[ $wp_locale ] ) ) {
				$counts[ $wp_locale ] = 0;
			}
			$counts[ $wp_locale ] += (int) $set->percent_translated;
		}
	}

	foreach ( $counts as $locale => $percent_translated ) {
		// English locales don't have wp/dev/cc.
		$projects_count = 0 === strpos( $locale, 'en_' ) ? 3 : 4;

		/*
		 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
		 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
		 */
		$percent_complete = 100 / ( 100 * $projects_count ) * $percent_translated;
		$percent_complete = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );

		$percentages[ $locale ] = $percent_complete;
	}

	set_transient( 'core_translation_data', $percentages, 30 * MINUTE_IN_SECONDS );

	return $percentages;
}

/**
 * Gets the main Rosetta site ID for a given locale.
 *
 * @param GP_Locale $locale The locale object.
 * @return int|null
 */
function get_locale_site_id( GP_Locale $locale ): ?int {
	static $site_ids = [];

	if ( array_key_exists( $locale->wp_locale, $site_ids ) ) {
		return $site_ids[ $locale->wp_locale ];
	}

	$result = get_sites(
		[
			'locale'     => $locale->wp_locale,
			'network_id' => WPORG_GLOBAL_NETWORK_ID,
			'path'       => '/',
			'fields'     => 'ids',
			'number'     => '1',
		]
	);

	$site_id = array_shift( $result );
	$site_ids[ $locale->wp_locale ] = $site_id ? (int) $site_id : null;

	return $site_ids[ $locale->wp_locale ];
}

/**
 * Retrieves prepared users for a given site ID and role.
 *
 * @param int    $site_id Site ID.
 * @param string $role    User role slug.
 * @return array
 */
function get_users_by_role( int $site_id, string $role ): array {
	$users = get_users(
		[
			'blog_id'     => $site_id,
			'role'        => $role,
			'count_total' => false,
		]
	);

	$prepared_users = [];
	foreach ( $users as $user ) {
		$prepared_users[ $user->user_nicename ] = prepare_user( $user );
	}

	uasort( $prepared_users, fn( $a, $b ) => strnatcasecmp( $a['display_name'], $b['display_name'] ) );

	return $prepared_users;
}

/**
 * Prepares user objects for output.
 *
 * @param \WP_User $user The user.
 * @return array List of user data.
 */
function prepare_user( WP_User $user ): array {
	return [
		'display_name' => $user->display_name ?: $user->user_nicename,
		'email'        => $user->user_email,
		'nice_name'    => $user->user_nicename,
		'slack'        => get_slack_username( $user->ID ),
	];
}

/**
 * Gets the current and past translation contributors for the given locale.
 *
 * @param GP_Locale $locale                The locale object.
 * @param int       $active_days_threshold Days to consider a contributor "active" (default: 365).
 * @return array{translators: array, translators_past: array}
 */
function get_translation_contributors( GP_Locale $locale, int $active_days_threshold = 365 ): array {
	global $wpdb;

	$empty_result = [
		'translators'      => [],
		'translators_past' => [],
	];

	[ $locale_name, $locale_slug ] = array_merge( explode( '/', $locale->slug ), [ 'default' ] );

	$contributions = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT user_id, MAX(date_modified) AS latest_date
			 FROM translate_user_translations_count
			 WHERE accepted > 0 AND locale = %s AND locale_slug = %s
			 GROUP BY user_id',
			$locale_name,
			$locale_slug
		)
	);

	if ( empty( $contributions ) ) {
		return $empty_result;
	}

	$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$active_days_threshold} days" ) );
	$user_ids    = wp_list_pluck( $contributions, 'user_id' );
	$dates_by_id = wp_list_pluck( $contributions, 'latest_date', 'user_id' );

	$user_ids_list = implode( ',', array_map( 'intval', $user_ids ) );
	$user_rows     = $wpdb->get_results( "SELECT ID, user_nicename, display_name FROM {$wpdb->users} WHERE ID IN ($user_ids_list)" );

	$translators      = [];
	$translators_past = [];

	foreach ( $user_rows as $user ) {
		$entry = [
			'display_name' => $user->display_name ?: $user->user_nicename,
			'nice_name'    => $user->user_nicename,
		];

		if ( ( $dates_by_id[ $user->ID ] ?? '' ) >= $cutoff_date ) {
			$translators[ $user->user_nicename ] = $entry;
		} else {
			$translators_past[ $user->user_nicename ] = $entry;
		}
	}

	uasort( $translators, fn( $a, $b ) => strnatcasecmp( $a['display_name'], $b['display_name'] ) );
	uasort( $translators_past, fn( $a, $b ) => strnatcasecmp( $a['display_name'], $b['display_name'] ) );

	return [
		'translators'      => $translators,
		'translators_past' => $translators_past,
	];
}

/**
 * Determines the release status of the given locale,
 */
function get_locale_release_status( string $rosetta_site_url, string $latest_release ): string {
	if ( ! $rosetta_site_url ) {
		return 'no-site';
	}

	if ( ! $latest_release ) {
		return 'no-releases';
	}

	$one_lower = \floatval( WP_CORE_LATEST_RELEASE ) - 0.1;

	if ( $latest_release == WP_CORE_LATEST_RELEASE ) {
		return 'latest';
	} elseif ( substr( $latest_release, 0, 3 ) == substr( WP_CORE_LATEST_RELEASE, 0, 3 ) ) {
		return 'minor-behind';
	} elseif ( substr( $latest_release, 0, 3 ) == substr( $one_lower, 0, 3 ) ) {
		return 'major-behind-one';
	} else {
		return 'major-behind-many';
	}
}

/**
 * Determines the translation status of the given locale.
 */
function get_locale_translation_status( int $percent_translated ): string {
	if ( $percent_translated == 100 ) {
		return 'translated-100';
	} elseif ( $percent_translated >= 95 ) {
		return 'translated-95';
	} elseif ( $percent_translated >= 90 ) {
		return 'translated-90';
	} elseif ( $percent_translated >= 50 ) {
		return 'translated-50';
	} else {
		return 'translated-50-less';
	}
}

/**
 * Gets the Slack username for a .org user.
 *
 * @param int $user_id User ID.
 * @return string Slack username or empty string if not found.
 */
function get_slack_username( int $user_id ): string {
	static $slack_cache = [];

	if ( array_key_exists( $user_id, $slack_cache ) ) {
		return $slack_cache[ $user_id ];
	}

	global $wpdb;

	$slack_cache[ $user_id ] = '';

	$data = $wpdb->get_var( $wpdb->prepare( 'SELECT profiledata FROM slack_users WHERE user_id = %d', $user_id ) );
	if ( $data ) {
		$profile = json_decode( $data, true );

		if ( is_array( $profile ) && empty( $profile['deleted'] ) ) {
			$display_name = $profile['profile']['display_name'] ?? '';
			$real_name    = $profile['profile']['real_name'] ?? '';

			$slack_cache[ $user_id ] = $display_name ?: $real_name;
		}
	}

	return $slack_cache[ $user_id ];
}
