<?php

namespace WordPressdotorg\GlotPress\Customizations;

use GP;
use GP_Locales;
use GP_Translation;
use WordPressdotorg\GlotPress\Customizations\CLI\Stats;
use WordPressdotorg\GlotPress\Customizations\CLI\Duplicate_Translations;
use WP_CLI;
use WP_User;
use function WordPressdotorg\Profiles\assign_badge;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * @var array The IDs of the translations that have been imported.
	 */
	private array $imported_translation_ids = array();

	/**
	 * @var string The source of translations that have been imported.
	 */
	private string $imported_source = '';

	/**
	 * The cache key for the list of GTE email addresses.
	 *
	 * @var string
	 */
	const GTE_EMAIL_ADDRESSES = 'wporg_gte_email_addresses';

	/**
	 * The cache group for the list of GTE email addresses.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'wporg-translate';

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
		add_action( 'init', array( $this, 'bump_assets_versions' ), 20 );
		add_action( 'init', array( $this, 'add_cors_header' ) );
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		add_filter( 'body_class', array( $this, 'wporg_add_make_site_body_class' ) );
		add_filter( 'gp_translation_row_template_more_links', array( $this, 'add_consistency_tool_link' ), 10, 5 );
		add_filter( 'gp_translation_prepare_for_save', array( $this, 'apply_capital_P_dangit' ), 10, 2 );
		add_filter( 'gp_original_extracted_comments', array( $this, 'format_translator_commments' ), 15 );
		add_filter( 'wporg_translate_language_pack_theme_args', array( $this, 'set_version_for_default_themes_in_development' ), 10, 2 );

		add_filter( 'gp_translation_prepare_for_save', array( $this, 'auto_reject_already_rejected' ), 10, 2 );
		add_action( 'gp_translation_created', array( $this, 'auto_reject_replaced_suggestions' ) );

		add_filter( 'gp_for_translation_clauses', array( $this, 'allow_searching_for_no_author_translations' ), 10, 3 );

		add_filter( 'gp_custom_reasons', array( $this, 'get_custom_reasons' ), 10, 2 );

		// Cron.
		add_filter( 'cron_schedules', [ $this, 'register_cron_schedules' ] );
		add_action( 'init', [ $this, 'register_cron_events' ] );
		add_action( 'wporg_translate_update_contributor_profile_badges', [ $this, 'update_contributor_profile_badges' ] );
		add_action( 'wporg_translate_update_polyglots_stats', [ $this, 'update_polyglots_stats' ] );
		add_action( 'wporg_translate_duplicate_translations', [ $this, 'duplicate_translations' ] );
		add_action( 'gp_translation_created', array( $this, 'log_translation_source' ) );
		add_action( 'gp_translation_saved', array( $this, 'log_translation_source' ) );
		add_action( 'gp_translations_imported', array( $this, 'log_imported_translations' ) );

		// Toolbar.
		add_action( 'admin_bar_menu', array( $this, 'add_profile_settings_to_admin_bar' ) );
		add_action( 'admin_bar_menu', array( $this, 'replace_login_url_in_admin_bar' ), 20 );
		add_action( 'admin_bar_init', array( $this, 'show_admin_bar' ) );
		add_action( 'add_admin_bar_menus', array( $this, 'remove_admin_bar_menus' ) );

		// Load the API endpoints.
		add_action( 'rest_api_init', array( __NAMESPACE__ . '\REST_API\Base', 'load_endpoints' ) );

		//Locales\Serbian_Latin::init();

		// Correct `WP_Locale` for variant locales in project lists.
		add_filter( 'gp_translation_sets_sort', [ $this, 'filter_gp_translation_sets_sort' ] );

		// create permission for the translation events.
		add_filter( 'user_has_cap', array( $this, 'gp_translation_events_can_create_events' ), 10, 4 );

		// Add site tour items.
		if ( isset( $_GET['site_tour'] ) && 'test' == $_GET['site_tour'] ) {
			add_filter(
				'gp_tour',
				function() {
					return array(
						'ui-intro' => [
							[
								'title' => 'UI Introduction Tour',
								'color' => '#3939c7',
							],
							[
								'selector'         => '.source-string__singular',
								'html'             => 'This is the original string, pay attention to underlined words: they are in the glossary of your locale and if the context fits you must use the translation suggested by the glossary',
								'popover-position' => 'left',
							],
							[
								'selector' => '.source-details__references',
								'html'     => 'This is the comments, context and references area: it can contain very useful information such as placheholders meaning, context of the string, a link to the code that could contain more comments about the use of that string.',
							],
							[
								'selector' => '.translation-wrapper .textareas',
								'html'     => 'This is the area where you can suggest your translation. Good grammar helps! Also, always pay attention to context and the Glossary entries that may apply.',
							],
							[
								'selector' => '.translation-actions',
								'html'     => 'This is the buttons area where you can find the very helpful “Help” button!',
							],
							[
								'selector' => '.suggestions-wrapper',
								'html'     => 'This is the Translations memory and Other languages area, remember Translation Memory could be a very useful help, but it all depends on the context. Context is all!',
							],
							[
								'selector' => '.sidebar-tabs',
								'html'     => 'This is the meta information area, Discussion and History can give more context information about the string, Make sure you always check these as well, if available.',
							],
							[
								'selector' => '.translation-actions__primary',
								'html'     => 'When you\'re happy with your translation click on the “Suggest” button',
							],
						],
					);
				},
				10
			);
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
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
		if ( ! wp_next_scheduled( 'wporg_translate_update_polyglots_stats' ) ) {
			wp_schedule_event( time(), 'daily', 'wporg_translate_update_polyglots_stats' );
		}
		if ( ! wp_next_scheduled( 'wporg_translate_duplicate_translations' ) ) {
			wp_schedule_event( time(), 'daily', 'wporg_translate_duplicate_translations' );
		}
	}

	/**
	 * Calls the profile.w.org activity handler to assign contributors to the
	 * Translation Contributor group.
	 */
	public function update_contributor_profile_badges() {
		global $wpdb;

		if ( ! function_exists( 'WordPressdotorg\Profiles\assign_badge' ) ) {
			return;
		}

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

		assign_badge( 'translation-contributor', $user_ids );

		update_option( 'wporg_translate_last_badges_sync', $now );
	}

	/**
	 * Updates the Polyglots stats at https://make.wordpress.org/polyglots/stats/
	 *
	 * @return void
	 */
	public function update_polyglots_stats() {
		// Increase the memory limit during the cron request to support the memory-heavy stats cron.
		ini_set( 'memory_limit', '1G' );

		$stats = new Stats();
		$stats();
	}

	/**
	 * Detects duplicate translations in the database in "current" status and sends a notification to Slack.
	 *
	 * @return void
	 */
	public function duplicate_translations() {
		$duplicates = new Duplicate_Translations();
		$duplicates( false, true, false, false );
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
	 * Stores source of a translation in database.
	 *
	 * @param GP_Translation $translation Translation instance.
	 * @return void
	 */
	public function log_translation_source( GP_Translation $translation ) {
		static $already_logged = array();
		$key                   = ! $translation->translation_0 ? null : $translation->translation_0;
		if ( isset( $already_logged[ $key ] ) ) {
			return;
		}
		$already_logged[ $key ] = true;
		$source                 = '';
		if ( $translation && is_object( GP::$current_route ) && 'GP_Route_Translation' === GP::$current_route->class_name ) {
			if ( 'import_translations_post' === GP::$current_route->last_method_called ) {
				$this->imported_translation_ids[] = $translation->id;

				if ( isset( $_POST['source'] ) && 'translate-live' == $_POST['source'] ) {
					$this->imported_source = 'playground';
				} elseif ( ! isset( $_POST['source'] ) && 'Import' == $_POST['submit'] ) {
					$this->imported_source = 'import';
				} else {
					return;
				}
			}
			if ( 'translations_post' === GP::$current_route->last_method_called ) {
				if ( isset( $_POST['translation_source'] ) && 'frontend' == $_POST['translation_source'] ) {
					$source = 'frontend';
					if ( isset( $_POST['externalTranslationSource'] ) ) {
						$suggestion_source     = sanitize_text_field( $_POST['externalTranslationSource'] );
						$suggested_translation = sanitize_text_field( $_POST['externalTranslationUsed'] );
						$this->save_translation_suggestion_source( $translation, $suggested_translation, $suggestion_source );
					}
				}
			}
		}
		if ( $source ) {
			gp_update_meta( $translation->id, 'source', $source, 'translation' );
		}
	}

	/**
	 * Save the source of a translation suggestion.
	 *
	 * @param object $translation Translation object.
	 * @param string $suggested_translation Suggested translation string.
	 * @param string $suggestion_source Suggestion source.
	 *
	 * @return void
	 */
	private function save_translation_suggestion_source( $translation, $suggested_translation, $suggestion_source ) {
		$suggestion_used = $suggestion_source;
		if ( $translation->translation_0 !== $suggested_translation ) {
			$suggestion_used .= '_modified';
		}
		if ( $suggestion_used ) {
			gp_update_meta( $translation->id, 'suggestion_used', $suggestion_used, 'translation' );
		}

	}

	/**
	 * Logs imported translations.
	 *
	 * @return void
	 */
	public function log_imported_translations() {
		global $wpdb;
		$source = $this->imported_source;
		if ( ! $source && ! $this->imported_translation_ids ) {
			return;
		}
		$sql        = 'INSERT INTO ' . $wpdb->gp_meta . ' (object_type, object_id, meta_key, meta_value) VALUES ';
		$sql_vars   = array();
		$sql_values = array_map(
			function( $translation_id ) use ( $source, &$sql_vars ) {
				$sql_vars[] = $translation_id;
				$sql_vars[] = $source;
				return '( "translation", %d, "source", %s )';
			},
			$this->imported_translation_ids
		);
		$sql       .= implode( ', ', $sql_values );
		$wpdb->query( $wpdb->prepare( $sql, $sql_vars ) );

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
	 * Makes admin bar compatible with GlotPress' custom header
	 * and script loader.
	 */
	public function show_admin_bar() {
		add_action( 'gp_head', 'wp_enqueue_admin_bar_header_styles' );
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
		WP_CLI::add_command( 'wporg-translate duplicate-translations', __NAMESPACE__ . '\CLI\Duplicate_Translations_CLI', $args = [] );
		WP_CLI::add_command( 'wporg-translate init-locale', __NAMESPACE__ . '\CLI\Init_Locale' );
		WP_CLI::add_command( 'wporg-translate language-pack', __NAMESPACE__ . '\CLI\Language_Pack' );
		WP_CLI::add_command( 'wporg-translate mass-create-sets', __NAMESPACE__ . '\CLI\Mass_Create_Sets' );
		WP_CLI::add_command( 'wporg-translate make-core-pot', __NAMESPACE__ . '\CLI\Make_Core_Pot' );
		WP_CLI::add_command( 'wporg-translate export', __NAMESPACE__ . '\CLI\Export' );
		WP_CLI::add_command( 'wporg-translate export-json', __NAMESPACE__ . '\CLI\Export_Json' );
		WP_CLI::add_command( 'wporg-translate show-stats', __NAMESPACE__ . '\CLI\Stats_Print' );

	}

	/**
	 * Allow the Playground to access translate.wordpress.org.
	 */
	public static function add_cors_header() {
		if ( headers_sent() ) {
			return;
		}
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			switch ( $_SERVER['HTTP_ORIGIN'] ) {
				case 'https://playground.wordpress.net':
					header( 'Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'] );
			}
		}
		header( 'Vary: origin' );
	}

	/**
	 * Changes the versions of GlotPress default assets for extra cache busting.
	 */
	public function bump_assets_versions() {
		$scripts = wp_scripts();
		foreach ( [ 'gp-common', 'gp-editor', 'gp-glossary', 'gp-translations-page', 'gp-mass-create-sets-page' ] as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) ) {
				$scripts->registered[ $handle ]->ver = $scripts->registered[ $handle ]->ver . '-1';
			}
		}

		$styles = wp_styles();
		foreach ( [ 'gp-base' ] as $handle ) {
			if ( isset( $styles->registered[ $handle ] ) ) {
				$styles->registered[ $handle ]->ver = $styles->registered[ $handle ]->ver . '-1';
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
	 * Filter the project translation set list to have correct WP_Locale fields for variants.
	 *
	 * This also affects the API output, so as not to have duplicate `wp_locale` fields with variants.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/4367
	 *
	 * @param array $translation_sets The translation sets.
	 * @return array Filtered translation sets.
	 */
	function filter_gp_translation_sets_sort( $translation_sets ) {
		foreach ( $translation_sets as $set ) {
			if ( 'default' !== $set->slug && ! str_contains( $set->wp_locale, $set->slug ) ) {
				$set->wp_locale .= '_' . $set->slug;
			}
		}

		return $translation_sets;
	}

	public function get_custom_reasons( $default_reasons, $locale ) {
		$locale_reasons = array(
			'it' => array(
				'consistency'          => array(
					'name'        => 'Consistenza',
					'explanation' => 'Utilizzare una traduzione consistente',
				),
				'second_person'        => array(
					'name'        => '2a persona',
					'explanation' => 'Per i verbi utilizziamo la seconda persona singolare rivolgendoci direttamente all’utente',
				),
				'capitalize_titlecase' => array(
					'name'        => 'No maiuscole TitleCase',
					'explanation' => 'Verificare il corretto uso delle maiuscole in italiano, sono presenti maiuscole non necessarie/errate',
				),
				'double_space'         => array(
					'name'        => 'Spazio doppio',
					'explanation' => 'Sono presenti uno o più uno spazi doppi',
				),
				'beginning_space'      => array(
					'name'        => 'Spazio all’inizio o alla fine',
					'explanation' => 'Sono presenti/assenti spazi all’inizio o alla fine della traduzione diversamente dalla stringa originale',
				),
				'no_s_plural'          => array(
					'name'        => 'Niente s al plurale',
					'explanation' => 'In italiano non si riportano le s del plurale dei termini che rimangono invariati',
				),
			),
		);

		$reasons = isset( $locale_reasons[ $locale ] ) ? $locale_reasons[ $locale ] : array();
		return array_merge( $default_reasons, $reasons );
	}

	/**
	 * Filter the permission to create events for the user.
	 *
	 * wp-org-translation-events plugin.
	 *
	 * @return array All caps the user has.
	 */
	public function gp_translation_events_can_create_events( $allcaps, $caps, $args, $user ): array {
		if ( in_array( 'create_translation_event', $caps, true ) ) {
			if ( GP::$permission->user_can( $user, 'admin' ) ) {
				$allcaps['create_translation_event'] = true;
			} elseif ( current_user_can( 'manage_options' ) ) {
				$allcaps['create_translation_event'] = true;
			} elseif ( self::is_user_a_wporg_gte( $user ) ) {
				$allcaps['create_translation_event'] = true;
			}
		}

		return $allcaps;
	}

	/**
	 * Indicates if the given user is a GTE at translate.wordpress.org.
	 *
	 * Caches the GTE email addresses for 12 hours.
	 *
	 * @param WP_User $user A user object.
	 *
	 * @return bool Whether the user is GTE for any of the languages to which the comments in the post belong.
	 */
	public static function is_user_a_wporg_gte( WP_User $user ): bool {
		$locales             = GP_Locales::locales();
		$gte_email_addresses = wp_cache_get( self::GTE_EMAIL_ADDRESSES, self::CACHE_GROUP );

		if ( false === $gte_email_addresses ) {
			$gte_email_addresses = array();
			foreach ( $locales as $locale ) {
				foreach ( self::get_gte_email_addresses( $locale->slug ) as $email ) {
					$gte_email_addresses[] = $email;
				}
			}
			$gte_email_addresses = array_unique( $gte_email_addresses );

			wp_cache_set( self::GTE_EMAIL_ADDRESSES, $gte_email_addresses, self::CACHE_GROUP, 12 * HOUR_IN_SECONDS );
		}

		if ( in_array( $user->user_email, $gte_email_addresses ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the general translation editors (GTE) emails for the given locale.
	 *
	 * @param string $locale The locale. E.g. 'zh-tw'.
	 *
	 * @return array The general translation editors (GTE) emails.
	 */
	public static function get_gte_email_addresses( string $locale ): array {
		$email_addresses = array();

		$gp_locale = GP_Locales::by_field( 'slug', $locale );
		if ( ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) || ( false === $gp_locale ) ) {
			return $email_addresses;
		}
		$result  = get_sites(
			array(
				'locale'     => $gp_locale->wp_locale,
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'path'       => '/',
				'fields'     => 'ids',
				'number'     => '1',
			)
		);
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return $email_addresses;
		}

		$users = get_users(
			array(
				'blog_id'     => $site_id,
				'role'        => 'general_translation_editor',
				'count_total' => false,
			)
		);
		foreach ( $users as $user ) {
			$email_addresses[] = $user->data->user_email;
		}

		return $email_addresses;
	}
}
