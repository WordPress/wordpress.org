<?php

// Locale detection.
require dirname( __FILE__ ) . '/inc/locale.php';

function browsehappy_get_browser_data( $browser = false ) {

	// In order to avoid non-English language translations of browser
	// descriptions from being invalidated due to string changes, until such time
	// as traslators submit translations, only English will immediately show the
	// latest strings. Other languages will temporarily show the outdated (but
	// translated at least) strings.
	$latest_strings = ( ! class_exists( 'Browse_Happy_Locale' ) || 0 === strpos( Browse_Happy_Locale::locale(), 'en' ) );

	$data = array(
		'chrome' => (object) array(
			'name' => 'Google Chrome',
			'long_name' => 'Google Chrome',
			'wikipedia' => 'Google_Chrome',
			'wikidata' => 'Q777',
			'normalized' => 1, // just first number
			'facebook' => 'googlechrome',
			'url' => class_exists( 'Browse_Happy_Locale' ) && 'zh_CN' === Browse_Happy_Locale::locale() ? 'https://www.google.cn/chrome' : 'https://www.google.com/chrome',
			'info' => ( $latest_strings ?
				__( '&#8220;Get more done with the new Google Chrome. A more simple, secure, and faster web browser than ever, with Google’s smarts built-in.&#8221;', 'browsehappy' )
				: __( '&#8220;A fast new browser from Google. Try&nbsp;it&nbsp;now!&#8221;', 'browsehappy' )
			),
		),
		'firefox' => (object) array(
			'name' => 'Mozilla Firefox',
			'long_name' => 'Mozilla Firefox',
			'wikipedia' => 'Firefox',
			'wikidata' => 'Q698',
			'normalized' => 1.5, // include second number if non-zero
			'facebook' => 'Firefox',
			'url' => 'https://www.mozilla.org/firefox/',
			'info' => ( $latest_strings ?
				 __( '&#8220;Faster page loading, less memory usage and packed with features, the new Firefox is here.&#8221;', 'browsehappy' )
				 : __( "&#8220;Your online security is Firefox's top priority. Firefox is free, and made to help you get the most out of the&nbsp;web.&#8221;", 'browsehappy' )
			),
		),
		'safari' => (object) array(
			'name' => 'Safari',
			'long_name' => 'Apple Safari',
			'wikipedia' => 'Safari',
			'wikidata' => 'Q35773',
			'normalized' => 1.5, // include second number if non-zero
			'facebook' => false,
			'url' => 'https://www.apple.com/safari/',
			'info' => ( $latest_strings ?
				__( '&#8220;Safari is faster and more energy efficient than other browsers. You can shop safely and simply in Safari on your Mac.&#8221;', 'browsehappy' )
				: str_replace( 'and Windows ', '', __( '&#8220;Safari for Mac and Windows from Apple, the world’s most innovative&nbsp;browser.&#8221;', 'browsehappy' ) )
			),
		),
		'opera' => (object) array(
			'name' => 'Opera',
			'long_name' => 'Opera',
			'wikipedia' => 'Opera',
			'wikidata' => 'Q41242',
			'normalized' => 1, // just first number
			'facebook' => 'Opera',
			'url' => 'https://www.opera.com/',
			'info' => ( $latest_strings ?
				__( '&#8220;Opera is a secure, innovative browser used by millions around the world with a built-in ad blocker, free VPN, and much more - all for your best browsing experience.&#8221;', 'browsehappy' )
				: __( '&#8220;The fastest browser on Earth—secure, powerful and easy to use, with excellent privacy protection. And&nbsp;it&nbsp;is&nbsp;free.&#8221;', 'browsehappy' )
			),
		),
		'edge' => (object) array(
			'name' => 'Microsoft Edge',
			'long_name' => 'Microsoft Edge',
			'wikipedia' => 'Microsoft_Edge',
			'wikidata' => 'Q18698690',
			'normalized' => 1, // just first number
			'facebook' => 'MicrosoftEdge',
			'url' => 'https://www.microsoft.com/edge',
			'info' => ( $latest_strings ?
				__( '&#8220;Microsoft Edge offers world-class performance with more privacy, more productivity, and more value while you browse.&#8221;', 'browsehappy' )
				: __( '&#8220;Microsoft Edge ranks first when put to real world page load tests. Whether you use the web to search, watch or play, this browser won&#8217;t slow you down.&#8221;', 'browsehappy' )
			),
		),
		'brave' => (object) array(
			'name' => 'Brave',
			'long_name' => 'Brave',
			'wikipedia' => 'Brave_(web_browser)',
			'wikidata' => 'Q22906900',
			'normalized' => 1.5, // include second number if non-zero
			'facebook' => 'bravetheinternet',
			'url' => 'https://brave.com/',
			'info' => __( '&#8220;The Brave browser is a fast, private and secure web browser for PC, Mac and mobile.&#8221;', 'browsehappy' ),
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

function browsehappy_fetch_version( $browser, $normalize = true, $rank = true ) {

	$fragment = browsehappy_get_browser_data( $browser )->wikidata;
	if ( ! $fragment ) {
		return false;
	}

	// Unexpiring transients are autoloaded. We expire these manually on cron instead.
	$stored_version = get_transient( 'browsehappy_version_' . $browser );
	if ( false !== $stored_version ) {
		if ( $normalize ) {
			return browsehappy_normalize_version( $browser, $stored_version );
		}
		return $stored_version;
	}

	$rank_type = $rank ? 'PreferredRank' : 'NormalRank';
	$limit     = $rank ? 'LIMIT 1' : '';

	// See https://github.com/WordPress/browsehappy/issues/37
	$query = "
		SELECT ?version WHERE {
			wd:{$fragment} p:P348 [
				ps:P348 ?version;
				pq:P548 wd:Q2804309;
				wikibase:rank wikibase:{$rank_type}
			].
		}
		ORDER BY DESC (?version) {$limit}
	";

	$request = wp_remote_get( add_query_arg(
		array(
			'format' => 'json',
			'query'  => rawurlencode( $query ),
		),
		'https://query.wikidata.org/bigdata/namespace/wdq/sparql'
	) );

	if ( is_wp_error( $request ) ) {
		return false;
	}

	$data = json_decode( wp_remote_retrieve_body( $request ) );

	if (
		empty( $data ) ||
		empty( $data->results ) ||
		! is_array( $data->results->bindings )
	) {
		return false;
	}

	if (
		empty( $data->results->bindings[0] ) ||
		empty( $data->results->bindings[0]->version ) ||
		empty( $data->results->bindings[0]->version->value )
	) {
		if ( $rank ) {
			return browsehappy_fetch_version( $browser, $normalize, false );
		} else {
			return false;
		}
	}

	if ( ! $rank ) {
		usort( $data->results->bindings, function( $a, $b ) {
			return strcmp( $b->version->value, $a->version->value );
		} );
	}

	$version = $data->results->bindings[0]->version->value;

	$version = preg_replace( '/[^0-9\.]/', '', $version );

	set_transient( 'browsehappy_version_' . $browser, $version );

	if ( $normalize ) {
		return browsehappy_normalize_version( $browser, $version );
	}

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

	/* translators: Enter either 'ltr' if target language is written left-to-right or 'rtl' if target language is written right-to-left. */
	$GLOBALS['wp_locale']->text_direction = _x( 'ltr', 'text direction', 'browsehappy' );
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
		<p><?php
			/* translators: "English" should be translated directly and not to the name of your language. */
			printf( __( 'Browse Happy is also available in English. <a href="%s">Click here to change the language to English</a>.', 'browsehappy' ), '/?locale=en' );
		?></p>
	</div>
	<?php
}
