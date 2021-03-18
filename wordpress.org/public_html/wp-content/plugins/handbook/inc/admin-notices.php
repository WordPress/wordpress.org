<?php
/**
 * Class to output helpful admin notices.
 *
 * @package handbook
 */

class WPorg_Handbook_Admin_Notices {

	/**
	 * Initializes functionality.
	 *
	 * @access public
	 */
	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'show_new_handbook_message' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_imported_handbook_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_imported_handbook_config_errors' ] );
	}

	/**
	 * Outputs admin notice showing tips for newly created handbook.
	 *
	 * @todo Maybe instead of hiding the message once posts are present it should persist as long as no landing page has been created?
	 *
	 * @access public
	 */
	public static function show_new_handbook_message() {
		global $wp_query;

		$current_screen = get_current_screen();

		// Only show message in listing of handbook posts when no posts are present yet.
		if (
			$current_screen
		&&
			'edit' === $current_screen->base
		&&
			in_array( $current_screen->post_type, wporg_get_handbook_post_types() )
		&&
			0 === $wp_query->post_count
		&&
			( empty( $wp_query->query_vars['post_status'] ) || 'publish' === $wp_query->query_vars['post_status'] )
		) {
			echo '<div class="notice notice-success"><p>';

			$suggested_slugs = array_unique( [
				str_replace( '-handbook', '', $current_screen->post_type ),
				'welcome',
				$current_screen->post_type,
				'handbook',
			] );
			$suggested_slugs = array_map( function( $x ) { return "<code>{$x}</code>"; }, $suggested_slugs );

			printf(
				/* translators: 1: example landing page title that includes post type name, 2: comma-separated list of acceptable post slugs */
				__( '<strong>Welcome to your new handbook!</strong> It is recommended that the first post you create is the landing page for the handbook. You can title it anything you like (suggestions: <code>%1$s</code> or <code>Welcome</code>). However, you must ensure that it has one of the following slugs: %2$s. The slug will ultimately be omitted from the page&#8216;s permalink URL, but will still appear in the permalinks for sub-pages.', 'wporg' ),
				WPorg_Handbook::get_name( $current_screen->post_type ),
				implode( ', ', $suggested_slugs )
			);
			echo "</p></div>\n";
		}
	}

	/**
	 * Outputs admin notice indicating the handbook is an imported handbook, if applicable.
	 *
	 * @access public
	 */
	public static function show_imported_handbook_notice() {
		global $wp_query;

		// Bail if handbook importer is not available.
		if ( ! class_exists( 'WPorg_Handbook_Importer' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		// Only show message in listing of handbook posts when no posts are present yet.
		if (
			$current_screen
		&&
			'edit' === $current_screen->base
		&&
			in_array( $current_screen->post_type, wporg_get_handbook_post_types() )
		&&
			WPorg_Handbook_Importer::is_handbook_imported( $current_screen->post_type )
		) {
			$handbook_config = WPorg_Handbook_Init::get_handbooks_config( $current_screen->post_type );

			$handbook = WPorg_Handbook_Init::get_handbook( $current_screen->post_type );
			if ( ! $handbook ) {
				return;
			}

			$importer = $handbook->get_importer();
			$interval = $importer ? $importer->get_cron_interval( false ) : [];
			$interval_display = ! empty( $interval['display'] ) ? strtolower( $interval['display'] ) : __( 'DISABLED', 'wporg' );

			echo '<div class="notice notice-info"><p>';
			printf(
				/* translators: 1: URL to remote manifest. 2: cron interval. */
				__( '<strong>This is an imported handbook!</strong> This handbook is imported according to a <a href="%1$s">remote manifest</a>. Any local changes will be overwritten during the next import, so make any changes at the remote location. Import interval: <strong>%2$s</strong>.', 'wporg' ),
				$handbook_config['manifest'],
				$interval_display
			);
			echo "</p></div>\n";
		}
	}

	/**
	 * Outputs admin error notice(s) for any misconfigured imported handbooks.
	 *
	 * @access public
	 */
	public static function show_imported_handbook_config_errors() {
		global $wp_query;

		// Bail if handbook importer is not available.
		if ( ! class_exists( 'WPorg_Handbook_Importer' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		// Only show message in listing of handbook posts when no posts are present yet.
		if (
			$current_screen
		&&
			'edit' === $current_screen->base
		&&
			in_array( $current_screen->post_type, wporg_get_handbook_post_types() )
		&&
			WPorg_Handbook_Importer::is_handbook_imported( $current_screen->post_type )
		) {
			$handbook_config = WPorg_Handbook_Init::get_handbooks_config( $current_screen->post_type );

			$handbook = WPorg_Handbook_Init::get_handbook( $current_screen->post_type );
			if ( ! $handbook ) {
				return;
			}

			$handbook_config = $handbook->get_config();
			$cron_intervals = wp_get_schedules();
			$interval_display = $handbook_config[ 'cron_interval' ] ?? '';

			if ( ! empty( $cron_intervals[ $interval_display ] ) ) {
				return;
			}

			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: cron interval. */
				__( '<strong>Misconfigured cron interval!</strong> This imported handbook has a misconfigured cron interval. The config defines an interval of <strong>%s</strong>, which has not been defined. The fallback import interval shown in a notice above includes the default cron interval currently in use.', 'wporg' ),
				$interval_display
			);
			echo "</p></div>\n";
		}
	}
}

add_action( 'plugins_loaded', [ 'WPorg_Handbook_Admin_Notices', 'init' ] );
