<?php

class GP_Help_Page_Plugin extends GP_Plugin {
	public $id = 'wporg-help';
	private $hide_notice = 'wporg_help_hide_notice';
	const handbook_link = 'https://make.wordpress.org/polyglots/handbook/tools/glotpress-translate-wordpress-org/';

	function __construct() {
		parent::__construct();
		$this->template_path = dirname( __FILE__ );
		$this->add_action( 'init' );
		$this->add_action( 'after_hello' );
		$this->add_action( 'after_notices' );
	}

	function init() {
		GP::$router->add( '/getting-started', array('GP_Help_Page_Plugin_Route', 'getting_started') );
		GP::$router->add( '/getting-started/', array('GP_Help_Page_Plugin_Route', 'getting_started') );
		GP::$router->add( '/getting-started/hide-notice', array('GP_Help_Page_Plugin_Route', 'hide_notice') );
	}

	function after_hello() {
		if ( GP::$user->logged_in() || $this->is_notice_hidden() ) {
			echo '<em><a class="secondary" href="' . self::handbook_link . '">Need help?</a></em>';
		}
	}

	function is_notice_hidden() {
		return ( gp_array_get( $_COOKIE, $this->hide_notice ) || ( GP::$user->logged_in() && GP::$user->current()->get_meta( $this->hide_notice ) ) );
	}

	function hide_notice() {
		if ( GP::$user->logged_in() ) {
			GP::$user->current()->set_meta( $this->hide_notice, true );
		}
		setcookie( $this->hide_notice, '1', time() + 3600*24*30, gp_url( '/' ) ); // a month
	}

	function after_notices() {
		if ( $this->is_notice_hidden() ) return;
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

class GP_Help_Page_Plugin_Route extends GP_Route {

	function __construct() {
		parent::__construct();
		$this->template_path = GP::$plugins->wporg_help->template_path;
	}

	function getting_started() {
		GP::$plugins->wporg_help->remove_action( 'after_notices' );

		gp_redirect( GP_Help_Page_Plugin::handbook_link, 301 );
		exit;
	}

	function hide_notice() {
		GP::$plugins->wporg_help->hide_notice();
	}

}

GP::$plugins->wporg_help = new GP_Help_Page_Plugin;
