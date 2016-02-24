<?php

// Locale detection.
//require dirname( __FILE__ ) . '/inc/locale.php';

function browsehappy_get_browser_data( $browser = false ) {

	$data = array(
		'chrome' => (object) array(
			'name' => 'Google Chrome',
			'long_name' => 'Google Chrome',
			'wikipedia' => 'Google_Chrome',
			'normalized' => 1, // just first number
			'facebook' => 'googlechrome',
			'url' => 'https://www.google.com/chrome',
			'info' => __( '&#8220;A fast new browser from Google. Try&nbsp;it&nbsp;now!&#8221;', 'browsehappy' ),
		),
		'firefox' => (object) array(
			'name' => 'Mozilla Firefox',
			'long_name' => 'Mozilla Firefox',
			'wikipedia' => 'Firefox',
			'normalized' => 1.5, // include second number if non-zero
			'facebook' => 'Firefox',
			'url' => 'https://www.firefox.com/',
			'info' => __( "&#8220;Your online security is Firefox's top priority. Firefox is free, and made to help you get the most out of the&nbsp;web.&#8221;", 'browsehappy' ),
		),
		'safari' => (object) array(
			'name' => 'Safari',
			'long_name' => 'Apple Safari',
			'wikipedia' => 'Safari',
			'normalized' => 1.5, // include second number if non-zero
			'facebook' => false,
			'url' => 'https://www.apple.com/safari/',
			'info' => str_replace( 'and Windows ', '', __( '&#8220;Safari for Mac and Windows from Apple, the world’s most innovative&nbsp;browser.&#8221;', 'browsehappy' ) ),
		),
		'opera' => (object) array(
			'name' => 'Opera',
			'long_name' => 'Opera',
			'wikipedia' => 'Opera',
			'normalized' => 1, // just first number
			'facebook' => 'Opera',
			'url' => 'http://www.opera.com/',
			'info' => __( '&#8220;The fastest browser on Earth—secure, powerful and easy to use, with excellent privacy protection. And&nbsp;it&nbsp;is&nbsp;free.&#8221;', 'browsehappy' ),
		),
		'ie' => (object) array(
			'name' => 'Internet Explorer',
			'long_name' => 'Microsoft Internet Explorer',
			'wikipedia' => 'Internet_Explorer',
			'normalized' => 1, // just first number
			'facebook' => 'internetexplorer',
			'url' => 'http://windows.microsoft.com/ie',
			'info' => __( '&#8220;Designed to help you take control of your privacy and browse with confidence. Free from&nbsp;Microsoft.&#8221;', 'browsehappy' ),
		),
	);
	if ( false === $browser )
		return $data;

	if ( ! isset( $data[ $browser ] ) )
		return false;

	return $data[ $browser ];
}

add_action( 'browsehappy_version', 'browsehappy_echo_version' );
add_filter( 'get_browsehappy_version', 'browsehappy_fetch_version' );

function browsehappy_echo_version( $browser ) {
	echo browsehappy_fetch_version( $browser );
}

function browsehappy_fetch_version( $browser, $normalize = true ) {

	$fragment = browsehappy_get_browser_data( $browser )->wikipedia;
	if ( ! $fragment )
		return false;

	// Unexpiring transients are autoloaded. We expire these manually on cron instead.
	$stored_version = get_transient( 'browsehappy_version_' . $browser );
	if ( false !== $stored_version ) {
		if ( $normalize )
			return browsehappy_normalize_version( $browser, $stored_version );
		return $stored_version;
	}

	$url = 'https://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvprop=content&format=php&titles=Template:Latest_stable_software_release/';
	$url .= $fragment;

	$response = wp_remote_get( $url );

	if ( is_wp_error( $response ) )
		return false;

	if ( ! $content = wp_remote_retrieve_body( $response ) )
		return false;

	if ( ! is_serialized( $content ) )
		return false;

	$content = maybe_unserialize( $content );
	$page = array_pop( $content['query']['pages'] );
	$raw_data = explode( "\n", $page['revisions'][0]['*'] );

	$version = false;
	foreach( $raw_data as $data ) {
		$data = trim( $data, '| ' );
		if ( false !== strpos( $data, 'Android' ) || false !== strpos( $data, 'iOS' ) )
			continue;
		if ( false !== strpos( $data, 'Linux' ) && false === strpos( $data, 'Mac OS X' ) && false === strpos( $data, 'Windows' ) )
			continue;
		if ( ( false !== $pos = strpos( $data, 'latest_release_version' ) ) || ( false !== $pos = strpos( $data, 'latest release version' ) ) ) {
			if ( $pos )
				$data = substr( $data, $pos );
			$version = trim( str_replace( array( 'latest_release_version', 'latest release version', '=' ), '', $data ), '| ' ) . " ";
			$version = str_replace( "'''Mac OS X''' and '''Microsoft Windows'''<br />", '', $version );
			$version = substr( $version, 0, strpos( $version, ' ' ) );
			break;
		}
	}

	if ( false === $version )
		return false;

	$version = preg_replace( '/[^0-9\.]/', '', $version );

	set_transient( 'browsehappy_version_' . $browser, $version );

	if ( $normalize )
		return browsehappy_normalize_version( $browser, $version );

	return $version;
}

function browsehappy_normalize_version( $browser, $version ) {

	$normalize = browsehappy_get_browser_data( $browser )->normalized;
	$version = explode( '.', $version );

	if ( 1.5 == $normalize ) {
		$return = $version[0];
		if ( '0' !== $version[1] )
			$return .= '.' . $version[1];
		return $return;
	}

	$return = array();
	for ( $i = 0; $i < $normalize; $i++ ) {
		$return[] = $version[ $i ];
	}
	return implode( '.', $return );
}

add_action( 'init', 'browsehappy_schedule_version_check' );

function browsehappy_schedule_version_check() {
	if ( ! wp_next_scheduled( 'browsehappy_clear_version_cache' ) )
		wp_schedule_event( time(), 'twicedaily', 'browsehappy_clear_version_cache' );
}

add_action( 'browsehappy_clear_version_cache', 'browsehappy_clear_version_cache' );

function browsehappy_clear_version_cache() {
	$browsers = array_keys( browsehappy_get_browser_data() );
	foreach ( $browsers as $browser )
		delete_transient( 'browsehappy_version_' . $browser );
}

add_action( 'browsehappy_like_button', 'browsehappy_like_button' );

function browsehappy_like_button( $browser ) {
	$facebook_page = browsehappy_get_browser_data( $browser )->facebook;
	if ( false === $facebook_page ) {
		echo '<p class="likebutton"></p>';
		return;
	}

?>
<p class="likebutton"><iframe src="https://www.facebook.com/plugins/like.php?href=https%3A%2F%2Fwww.facebook.com%2F<?php echo $facebook_page; ?>&amp;layout=button_count&amp;show_faces=false&amp;width=172&amp;action=like&amp;font=lucida+grande&amp;colorscheme=light&amp;height=20" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:172px; height:20px;"></iframe></p>
<?php
}

add_action( 'init', 'browsehappy_init' );

remove_action( 'template_redirect', 'wp_old_slug_redirect' );

// Runs at end of init. Supplants global WP object.
function browsehappy_init() {
	if ( false === get_option( 'rewrite_rules' ) )
		add_option( 'rewrite_rules', '' );

	global $wp;
	$wp = new BrowseHappy_WP;
}

// Short-circuit query and 404 handling on the front-end.
if ( is_admin() ) :
	class BrowseHappy_WP extends WP {}
else :
	class BrowseHappy_WP extends WP {
		function query_posts() { }
		function handle_404() {
			status_header( 200 );
		}
	}
endif;

function browsehappy_load_textdomain() {
	load_theme_textdomain( 'browsehappy', get_template_directory() . '/languages' );
}

add_action( 'after_setup_theme', 'browsehappy_load_textdomain' );

if ( function_exists( 'browsehappy_parse_user_agent' ) )
	add_action( 'browsehappy_browser_notice', 'browsehappy_browser_notice' );

function browsehappy_browser_notice() {
	$ua = $_SERVER['HTTP_USER_AGENT'];
	$results = browsehappy_parse_user_agent( $ua );
	if ( ! $results['upgrade'] )
		return;
	?>
	<div id="browser-status" class="wrap">
	<?php if ( $results['name'] == 'Internet Explorer' && strpos( $ua, 'Windows NT 5.' ) !== false ) : ?>
		<?php if ( $results['insecure'] ) : ?>
			<p><?php printf( __( 'It looks like you&#8217;re using an insecure version of %s.', 'browsehappy' ), $results['name'] ); ?>
                        <?php _e( 'Using an outdated browser makes your computer unsafe.', 'browsehappy' ); ?>
		<?php else : ?>
			<p><?php _e( 'It looks like you&#8217;re using an old version of Internet Explorer.', 'browsehappy' ); ?>
		<?php endif; ?>
			<?php _e( 'On Windows XP, you are unable to update to the latest version. For the best experience on the web, we suggest you try a new browser.', 'browsehappy' ); ?></p>
	<?php elseif ( $results['insecure'] ) : ?>
		<p class="browser-status-text"><?php printf( __( 'It looks like you&#8217;re using an insecure version of %s.', 'browsehappy' ), $results['name'] ); ?>
			<?php _e( 'Using an outdated browser makes your computer unsafe.', 'browsehappy' ); ?>
			<?php _e( 'For the best experience on the web, please update your browser.', 'browsehappy' ); ?></p>
		<p class="browser-status-action"><a href="<?php echo esc_url( $results['update_url'] ); ?>"><?php _e( 'Upgrade now!', 'browsehappy' ); ?></a></p>
	<?php else : ?>
		<p class="browser-status-text"><?php printf( __( 'Your browser is out of date! It looks like you&#8217;re using an old version of %s.', 'browsehappy' ), $results['name'] ); ?>
			<?php _e( 'For the best experience on the web, please update your browser.', 'browsehappy' ); ?></p>
		<p class="browser-status-action"><a href="<?php echo esc_url( $results['update_url'] ); ?>"><?php _e( 'Upgrade now!', 'browsehappy' ); ?></a></p>
	<?php endif; ?>
	</div>
	<?php
}

if ( class_exists( 'Browse_Happy_Locale' ) )
	add_action( 'browsehappy_locale_notice', 'browsehappy_locale_notice' );

function browsehappy_locale_notice() {
	if ( 0 === strpos( Browse_Happy_Locale::locale(), 'en' ) ) // && Browse_Happy_Locale::$guessed )
		return;
	?>
	<div id="i18n-alert">
		<p><?php printf( __( 'Browse Happy is also available in English. <a href="%s">Click here to change the language to English</a>.', 'browsehappy' ), '/?locale=en' ); ?></p>
	</div>
	<?php
}
