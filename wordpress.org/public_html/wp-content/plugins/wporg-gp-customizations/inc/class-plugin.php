<?php

namespace WordPressdotorg\GlotPress\Customizations;

use GP;
use GP_Locales;
use GP_Translation;
use WP_CLI;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	function plugins_loaded() {
		add_filter( 'gp_url_profile', array( $this, 'worg_profile_url' ), 10, 2 );
		add_filter( 'gp_projects', array( $this, 'natural_sort_projects' ), 10, 2 );
		add_action( 'gp_project_created', array( $this, 'update_projects_last_updated' ) );
		add_action( 'gp_project_saved', array( $this, 'update_projects_last_updated' ) );
		add_filter( 'pre_handle_404', array( $this, 'short_circuit_handle_404' ) );
		add_action( 'wp_default_scripts', array( $this, 'bump_script_versions' ) );
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		add_filter( 'body_class', array( $this, 'wporg_add_make_site_body_class' ) );
		add_filter( 'gp_translation_row_template_more_links', array( $this, 'add_consistency_tool_link' ), 10, 5 );
		add_filter( 'gp_translation_prepare_for_save', array( $this, 'apply_capital_P_dangit' ), 10, 2 );
		add_filter( 'gp_original_extracted_comments', array( $this, 'format_translator_commments' ), 15 );
		add_filter( 'wporg_translate_language_pack_theme_args', array( $this, 'set_version_for_default_themes_in_development' ), 10, 2 );

		add_filter( 'gp_translation_prepare_for_save', array( $this, 'auto_reject_already_rejected' ), 10, 2 );
		add_action( 'gp_translation_created', array( $this, 'auto_reject_replaced_suggestions' ) );

		add_filter( 'gp_for_translation_clauses', array( $this, 'allow_searching_for_no_author_translations' ), 10, 3 );

		// Cron.
		add_filter( 'cron_schedules', [ $this, 'register_cron_schedules' ] );
		add_action( 'init', [ $this, 'register_cron_events' ] );
		add_action( 'wporg_translate_update_contributor_profile_badges', [ $this, 'update_contributor_profile_badges' ] );

		// Toolbar.
		add_action( 'admin_bar_menu', array( $this, 'add_profile_settings_to_admin_bar' ) );
		add_action( 'admin_bar_menu', array( $this, 'replace_login_url_in_admin_bar' ), 20 );
		add_action( 'admin_bar_init', array( $this, 'show_admin_bar' ) );
		add_action( 'add_admin_bar_menus', array( $this, 'remove_admin_bar_menus' ) );

		add_action( 'template_redirect', array( $this, 'jetpack_stats' ), 1 );

		// Load the API endpoints.
		add_action( 'rest_api_init', array( __NAMESPACE__ . '\REST_API\Base', 'load_endpoints' ) );

		//Locales\Serbian_Latin::init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}

		// @todo temporary for new header/footer launch.
		add_filter( 'stylesheet', array( $this, 'switch_theme_for_new_header' ) );
		add_filter( 'template', array( $this, 'switch_theme_for_new_header' ) );
	}

	/**
	 * Defines a version for WordPress default themes which are still in development.
	 *
	 * @param array  $args WP-CLI arguments.
	 * @param string $slug Slug of a theme.
	 * @return array Filtered WP-CLI arguments.
	 */
	public function set_version_for_default_themes_in_development(  $args, $slug ) {
		if ( 'twentytwentyone' !== $slug || ! empty( $args['version'] ) ) {
			return $args;
		}

		$args['version'] = '1.0';

		return $args;
	}

	/**
	 * Splits multiple translator comments for an original into separate lines.
	 */
	public function format_translator_commments( $comments ) {
		$comment_parts = preg_split( '#(^|\n)translators: #i', $comments, 0, PREG_SPLIT_NO_EMPTY );
		if ( ! $comment_parts ) {
			return $comments;
		}

		return implode( '<br/>', $comment_parts );
	}

	/**
	 * Filters the non-default cron schedules.
	 *
	 * @param array $schedules An array of non-default cron schedules.
	 * @return array  An array of non-default cron schedules.
	 */
	public function register_cron_schedules( $schedules ) {
		$schedules['15_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Every 15 minutes',
		);

		return $schedules;
	}

	/**
	 * Registers cron events.
	 */
	public function register_cron_events() {
		if ( ! wp_next_scheduled( 'wporg_translate_update_contributor_profile_badges' ) ) {
			wp_schedule_event( time(), '15_minutes', 'wporg_translate_update_contributor_profile_badges' );
		}
	}

	/**
	 * Calls the profile.w.org activity handler to assign contributors to the
	 * Translation Contributor group.
	 */
	public function update_contributor_profile_badges() {
		global $wpdb;

		if ( ! isset( $wpdb->user_translations_count ) ) {
			return;
		}

		$now       = time();
		$last_sync = get_option( 'wporg_translate_last_badges_sync' );
		if ( ! $last_sync ) {
			return;
		}

		$user_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT user_id
			FROM {$wpdb->user_translations_count}
			WHERE accepted > 0 AND date_modified > %s
			GROUP BY user_id
		", gmdate( 'Y-m-d H:i:s', $last_sync ) ) );

		if ( ! $user_ids ) {
			update_option( 'wporg_translate_last_badges_sync', $now );
			return;
		}

		$request_body = [
			'action'      => 'wporg_handle_association',
			'source'      => 'polyglots',
			'command'     => 'add',
			'association' => 'translation-contributor',
		];

		foreach( $user_ids as $user_id ) {
			$request_body['user_id'] = $user_id;

			wp_remote_post( 'https://profiles.wordpress.org/wp-admin/admin-ajax.php', [
				'body'       => $request_body,
				'user-agent' => 'WordPress.org Translate',
			] );
		}

		update_option( 'wporg_translate_last_badges_sync', $now );
	}

	/**
	 * Applies capital_P_dangit() on translations.
	 *
	 * @param array          $args        Translation arguments.
	 * @param GP_Translation $translation Translation instance.
	 * @return array Translation arguments.
	 */
	public function apply_capital_P_dangit( $args, GP_Translation $translation ) {
		foreach ( range( 0, $translation->get_static( 'number_of_plural_translations' ) - 1 ) as $i ) {
			if ( isset( $args[ "translation_$i" ] ) ) {
				$args[ "translation_$i" ] = capital_P_dangit( $args[ "translation_$i" ] );
			}
		}

		return $args;
	}

	/**
	 * Rejects translation submissions where the suggested string has already been rejected for the user.
	 *
	 * @param array          $args        Translation arguments.
	 * @param GP_Translation $translation Translation instance.
	 * @return array Translation arguments.
	 */
	public function auto_reject_already_rejected( $args, GP_Translation $translation ) {
		if (
			! $translation->id &&
			! empty( $args['user_id'] ) &&
			'waiting' === $args['status'] &&
			GP::$current_route->class_name === 'GP_Route_Translation' &&
			GP::$current_route->last_method_called === 'translations_post'
		) {
			// New translation being added.

			$translation_set = GP::$translation_set->get( $args['translation_set_id'] );
			$project = GP::$project->get( $translation_set->project_id );

			// If the current user can approve / write to the project, skip.
			if (
				GP::$permission->current_user_can( 'approve', 'translation-set', $translation_set->id )
				||
				GP::$permission->current_user_can( 'write', 'project', $project->id )
			) {
				return $args;
			}

			$existing_rejected_translations = GP::$translation->for_translation(
				$project,
				$translation_set,
				'no-limit',
				array(
					'user_login'  => get_user_by( 'ID', $args['user_id'] )->user_login,
					'original_id' => $args['original_id'],
					'status'      => 'rejected',
				),
				array()
			);

			if ( $existing_rejected_translations ) {
				$locale = GP_Locales::by_slug( $translation_set->locale );

				$translations = [];
				foreach ( range( 0, GP::$translation->get_static( 'number_of_plural_translations' ) - 1 ) as $i ) {
					if ( isset( $args[ "translation_$i" ] ) ) {
						$translations[] = $args[ "translation_$i" ];
					}
				}

				foreach ( $existing_rejected_translations as $e ) {
					if ( array_pad( $translations, $locale->nplurals, null ) == $e->translations ) {
						GP::$current_route->die_with_error( __( 'Identical rejected translation already exists.', 'wporg' ), 200 );
					}
				}
			}

		}

		return $args;
	}

	/**
	 * Auto-Rejects a translators submissions when the translator submits a replacement suggestion.
	 *
	 * @see https://github.com/GlotPress/GlotPress-WP/issues/889
	 *
	 * @param GP_Translation $translation Translation instance.
	 */
	public function auto_reject_replaced_suggestions( GP_Translation $translation ) {
		// If the suggestion isn't in a waiting status, it's can be skipped, they've got better access than most.
		if ( 'waiting' !== $translation->status || ! $translation->user_id ) {
			return;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );
		$project = GP::$project->get( $translation_set->project_id );

		// If the current user can approve / write to the project, skip. Probably not needed due to the `waiting` check above.
		if (
			GP::$permission->current_user_can( 'approve', 'translation-set', $translation_set->id )
			||
			GP::$permission->current_user_can( 'write', 'project', $project->id )
		) {
			return;
		}

		$previous_translations = GP::$translation->for_translation(
			$project,
			$translation_set,
			'no-limit',
			array(
				'user_login'  => get_user_by( 'ID', $translation->user_id )->user_login,
				'original_id' => $translation->original_id,
				'status'      => 'waiting_or_fuzzy',
			),
			array()
		);

		// This will include the current translation, so skip that.
		foreach ( $previous_translations as $prev_translation ) {
			if ( $prev_translation->id >= $translation->id ) {
				// >= to match the current translation and any race-condition situations
				continue;
			}

			// Previous translation from this translator, reject it.
			GP::$translation->get( $prev_translation->id )->reject();
		}
	}

	/**
	 * Allow searching for translations by user_id 0, by setting the user_login field to `0` or `anonymous`.
	 */
	function allow_searching_for_no_author_translations( $clauses, $set, $filters ) {
		$user_login = gp_array_get( $filters, 'user_login' );
	
		if ( '0' === $user_login ) {
			$clauses['where'] .= ( $clauses['where'] ? ' AND' : '' ) . ' t.user_id = 0';
		} elseif ( 'anonymous' === $user_login ) {
			// 'Anonymous' user exists, but has no translations.
			$clauses['where'] = preg_replace( '/(user_id\s*=\s*\d+)/', 'user_id = 0', $clauses['where'] );
		}
	
		return $clauses;
	}

	/**
	 * Adds a link to view originals in consistency tool.
	 *
	 * @param array              $more_links      The links to be output.
	 * @param GP_Project         $project         Project object.
	 * @param GP_Locale          $locale          Locale object.
	 * @param GP_Translation_Set $translation_set Translation Set object.
	 * @param GP_Translation     $translation     Translation object.
	 */
	public function add_consistency_tool_link( $more_links, $project, $locale, $translation_set, $translation ) {
		$consistency_tool_url = add_query_arg(
			[
				'search' => urlencode( $translation->singular ),
				'set'    => urlencode( $locale->slug . '/' . $translation_set->slug ),
			],
			home_url( '/consistency' )
		);

		$more_links['consistency-tool'] = '<a href="' . esc_url( $consistency_tool_url ) . '">View original in consistency tool</a>';

		return $more_links;
	}

	/**
	 * Adds support for Jetpack Stats.
	 */
	public function jetpack_stats() {
		if ( ! function_exists( 'stats_hide_smile_css' ) ) {
			return;
		}

		add_action( 'gp_head', 'stats_hide_smile_css' );
		add_action( 'gp_head', 'stats_admin_bar_head', 100 );
		add_action( 'gp_footer', 'stats_footer', 101 );
	}

	/**
	 * Makes admin bar compatible with GlotPress' custom header
	 * and script loader.
	 */
	public function show_admin_bar() {
		add_action( 'gp_head', 'wp_admin_bar_header' );
		add_action( 'gp_head', '_admin_bar_bump_cb' );

		gp_enqueue_script( 'admin-bar' );
		gp_enqueue_style( 'admin-bar' );
	}

	/**
	 * Adds the link to profile settings to the user actions toolbar menu.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 */
	public function add_profile_settings_to_admin_bar( $wp_admin_bar ) {
		$logout_node = $wp_admin_bar->get_node( 'logout' );
		$wp_admin_bar->remove_node( 'logout' );

		$wp_admin_bar->add_node( [
			'parent' => 'user-actions',
			'id'     => 'gp-profile-settings',
			'title'  => 'Translate Settings',
			'href'   => gp_url( '/settings' ),
		] );

		if ( $logout_node ) {
			$wp_admin_bar->add_node( $logout_node ); // Ensures that logout is the last action.
		}
	}

	/**
	 * Changes the login URL to include a redirect parameter for the current page.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 */
	public function replace_login_url_in_admin_bar( $wp_admin_bar ) {
		if ( ! $wp_admin_bar->get_node( 'log-in' ) ) {
			return;
		}

		$menu = [
			'id' => 'log-in',
			'href' => wp_login_url( gp_url_current() ),
		];
		$wp_admin_bar->add_menu( $menu );
	}

	/**
	 * Removes default toolbar menus which are not needed.
	 */
	public function remove_admin_bar_menus() {
		remove_action( 'admin_bar_menu', 'wp_admin_bar_search_menu', 4 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu', 70 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_edit_menu', 80 );
	}

	/**
	 * Registers a menu location for a sub-navigation.
	 */
	public function after_setup_theme() {
		register_nav_menu( 'wporg_header_subsite_nav', 'WordPress.org Header Sub-Navigation' );
		register_nav_menu( 'wporg_translate_global_resources', 'Global Handbook Resources' );
	}

	/**
	 * Adds the CSS classes from make/polyglots to the body to sync the headline icon.
	 */
	function wporg_add_make_site_body_class( $classes ) {
		$classes[] = 'wporg-make';
		$classes[] = 'make-polyglots';
		return $classes;
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	public function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate init-locale', __NAMESPACE__ . '\CLI\Init_Locale' );
		WP_CLI::add_command( 'wporg-translate language-pack', __NAMESPACE__ . '\CLI\Language_Pack' );
		WP_CLI::add_command( 'wporg-translate mass-create-sets', __NAMESPACE__ . '\CLI\Mass_Create_Sets' );
		WP_CLI::add_command( 'wporg-translate make-core-pot', __NAMESPACE__ . '\CLI\Make_Core_Pot' );
		WP_CLI::add_command( 'wporg-translate export', __NAMESPACE__ . '\CLI\Export' );
		WP_CLI::add_command( 'wporg-translate export-json', __NAMESPACE__ . '\CLI\Export_Json' );

	}

	/**
	 * Changes the versions of some default scripts for cache bust.
	 *
	 * @see https://wordpress.slack.com/archives/meta-i18n/p1460626195000251
	 *
	 * @param WP_Scripts &$scripts WP_Scripts instance, passed by reference.
	 */
	public function bump_script_versions( &$scripts ) {
		foreach ( [ 'gp-editor', 'gp-glossary' ] as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) && '20160329' === $scripts->registered[ $handle ]->ver ) {
				$scripts->registered[ $handle ]->ver = '20160329a';
			}
		}
	}

	/**
	 * Short circuits WordPress' status handler to prevent unnecessary headers
	 * which prevent caching.
	 *
	 * @return bool True if a request for GlotPress, false if not.
	 */
	public function short_circuit_handle_404() {
		if ( is_glotpress() ) {
			return true;
		}

		return false;
	}

	/**
	 * Stores the timestamp of the last update for projects.
	 *
	 * Used by the Rosetta Roles plugin to invalidate local caches.
	 */
	public function update_projects_last_updated() {
		update_option( 'wporg_projects_last_updated', time() );
	}

	/**
	 * Returns the profile.wordpress.org URL of a user.
	 *
	 * @param string $url      Current profile URL.
	 * @param string $nicename The URL-friendly user name.
	 * @return string profile.wordpress.org URL
	 */
	public function worg_profile_url( $url, $nicename ) {
		return 'https://profiles.wordpress.org/' . $nicename . '/';
	}

	/**
	 * Natural sorting for sub-projects, and attach whitelisted meta.
	 *
	 * @param GP_Project[] $sub_projects List of sub-projects.
	 * @param int          $parent_id    Parent project ID.
	 * @return GP_Project[] Filtered sub-projects.
	 */
	public function natural_sort_projects( $sub_projects, $parent_id ) {
		if ( in_array( $parent_id, array( 1, 13, 58 ) ) ) { // 1 = WordPress, 13 = BuddyPress, 58 = bbPress
			usort( $sub_projects, function( $a, $b ) {
				return - strcasecmp( $a->name, $b->name );
			} );
		}

		if ( in_array( $parent_id, array( 17, 523 ) ) ) { // 17 = Plugins, 523 = Themes
			usort( $sub_projects, function( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			} );
		}

		// Attach wp-themes meta keys
		if ( 523 == $parent_id ) {
			foreach ( $sub_projects as $project ) {
				$project->non_db_field_names = array_merge( $project->non_db_field_names, array( 'version', 'screenshot' ) );
				$project->version = gp_get_meta( 'wp-themes', $project->id, 'version' );
				$project->screenshot = esc_url( gp_get_meta( 'wp-themes', $project->id, 'screenshot' ) );
			}
		}

		return $sub_projects;
	}

	/**
	 * Localize any WordPress.org links.
	 *
	 * @param string $content   The content to search for WordPress.org links in.
	 * @param string $wp_locale The WP_Locale subdomain that the content should reference.
	 * @return string Filtered $content where any WordPress.org links have been replaced with $wp_locale subdomain links.
	 */
	function localize_links( $content, $wp_locale ) {
		global $wpdb;

		static $subdomains = [];
		if ( ! isset( $subdomains[ $wp_locale ] ) ) {
			$subdomains[ $wp_locale ] = $wpdb->get_var( $wpdb->prepare( 'SELECT subdomain FROM wporg_locales WHERE locale = %s LIMIT 1', $wp_locale ) );
		}

		if ( $subdomains[ $wp_locale ] ) {
			$content = preg_replace(
				'!(?<=[\'"])https?://wordpress.org/!i', // Only match when it's a url within an attribute.
				'https://' . $subdomains[ $wp_locale ] . '.wordpress.org/',
				$content
			);
		}

		return $content;
	}

	/**
	 * Switch to the `wporg-main` theme once the new header/footer launches.
	 *
	 * TwentyFifteen has a bunch of styles that interfere with the design, and we don't want them enqueued. We
	 * do want to enqueue all the other styles/scripts from Core/plugins, though.
	 * See `blocks/global-header-footer/classic-header.php`.
	 *
	 * @todo This can be removed after launch, but you must permanently switch the theme to `pub/wporg-main`.
	 *
	 * @param string $stylesheet
	 *
	 * @return string
	 */
	function switch_theme_for_new_header( $stylesheet ) {
		/*
		 * Otherwise this interferes with `render_global_styles()`, and we don't get all of the CSS variables
		 * from News.
		 */
		if ( ms_is_switched() ) {
			return $stylesheet;
		}

		if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
			if ( 'stylesheet' === current_filter() ) {
				$stylesheet = 'pub/wporg-main';
			} else {
				/* template */
				$stylesheet = 'pub/wporg';
			}
		} else {
			$stylesheet = 'core/twentyfifteen';
		}

		return $stylesheet;
	}
}
