<?php
/**
 * Plugin name: GlotPress: Help Page
 * Description: Provides a dismissable help notice for new translators on translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

class WPorg_GP_Help_Page {

	private $hide_notice = 'wporg_help_hide_notice';
	const handbook_link = 'https://make.wordpress.org/polyglots/handbook/tools/glotpress-translate-wordpress-org/';

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'after_hello', array( $this, 'after_hello' ) );
		add_action( 'gp_after_notices', array( $this, 'after_notices' ) );
	}

	function init() {
		GP::$router->add( '/getting-started/?', array( 'WPorg_GP_Help_Page_Route', 'getting_started' ) );
		GP::$router->add( '/getting-started/hide-notice', array( 'WPorg_GP_Help_Page_Route', 'hide_notice' ) );
	}

	function after_hello() {
		if ( is_user_logged_in() || $this->is_notice_hidden() ) {
			echo '<em><a class="secondary" href="' . self::handbook_link . '">Need help?</a></em>';
		}
	}

	function is_notice_hidden() {
		return ( gp_array_get( $_COOKIE, $this->hide_notice ) || ( is_user_logged_in() && get_user_meta( get_current_user_id(), $this->hide_notice ) ) );
	}

	function hide_notice() {
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), $this->hide_notice, true );
		}
		setcookie( $this->hide_notice, '1', time() + 3600*24*30, SITECOOKIEPATH, COOKIE_DOMAIN ); // a month
	}

	function after_notices() {
		if ( $this->is_notice_hidden() ) {
			return;
		}
		$hide_url = gp_url( '/getting-started/hide-notice' );
?>
		<div class="notice" id="help-notice">
			New to Translating WordPress?
			Read through our <a href="<?php echo self::handbook_link; ?>" target="_blank">Translator Handbook</a> to get started.
			<a id="hide-help-notice" class="secondary" style="float: right;" href="<?php echo esc_url( $hide_url ); ?>">Hide</a>
		</div>
		<script type="text/javascript">
			jQuery('#hide-help-notice').click(function() {
				jQuery.ajax({url: '<?php echo esc_js( $hide_url ); ?>'});
				jQuery('#help-notice').fadeOut(1000);
				return false;
			});
		</script>
<?php
	}

}

class WPorg_GP_Help_Page_Route extends GP_Route {

	function __construct() {
		parent::__construct();
	}

	function getting_started() {
		remove_action( 'gp_after_notices', array( wporg_gp_help_page(), 'after_notices' ) );

		wp_redirect( WPorg_GP_Help_Page::handbook_link, 301 );
		exit;
	}

	function hide_notice() {
		wporg_gp_help_page()->hide_notice();
	}

}

function wporg_gp_help_page() {
	global $wporg_gp_help_page;

	if ( ! isset( $wporg_gp_help_page ) ) {
		$wporg_gp_help_page = new WPorg_GP_Help_Page();
	}

	return $wporg_gp_help_page;
}
add_action( 'plugins_loaded', 'wporg_gp_help_page' );
