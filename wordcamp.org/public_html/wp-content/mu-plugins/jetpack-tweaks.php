<?php

namespace WordCamp\Jetpack_Tweaks;

/*
 * Open Graph Default Image.
 *
 * Provides a default image for sharing WordCamp home/pages to Facebook/Twitter/Google other than the Jetpack "blank" image.
 */
function default_og_image() {
	return 'https://s.w.org/images/backgrounds/wordpress-bg-medblue.png';
}
add_filter( 'jetpack_open_graph_image_default', __NAMESPACE__ . '\default_og_image' );

/**
 * Choose the default Open Graph image for single posts
 *
 * @param array $media
 * @param int   $post_id
 * @param array $args
 *
 * @return array
 */
function default_single_og_image( $media, $post_id, $args ) {
	if ( $media ) {
		return $media;
	}

	if ( has_site_icon() ) {
		$image_url = get_site_icon_url();
	} else if ( has_header_image() ) {
		$image_url = get_header_image();
	} else {
		$image_url = default_og_image();
	}

	return array( array(
		'type' => 'image',
		'from' => 'custom_fallback',
		'src'  => esc_url( $image_url ),
		'href' => get_permalink( $post_id ),
	) );
}
add_filter( 'jetpack_images_get_images', __NAMESPACE__ . '\default_single_og_image', 10, 3 );

/*
 * Add Twitter Card type.
 *
 * Added the twitter:card = summary OG tag for the home page and other ! is_singular() pages, which is not added by default by Jetpack.
 */
function add_og_twitter_summary( $og_tags ) {
	if ( is_home() || is_front_page() ) {
		$og_tags['twitter:card'] = 'summary';
	}

	return $og_tags;
}
add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\add_og_twitter_summary' );

/*
 * User @WordCamp as the default Twitter account.
 *
 * Add default Twitter account as @WordCamp for when individual WCs do not set their Settings->Sharing option for Twitter cards only.
 * Sets the "via" tag to blank to avoid slamming @WordCamp moderators with a ton of shared posts.
 */
function twitter_sitetag( $site_tag ) {
	if ( 'jetpack' == $site_tag ) {
		$site_tag = 'WordCamp';
		add_filter( 'jetpack_sharing_twitter_via', '__return_empty_string' );
	}

	return $site_tag;
}
add_filter( 'jetpack_twitter_cards_site_tag', __NAMESPACE__ . '\twitter_sitetag' );

/*
 * Determine which Jetpack modules should be automatically activated when new sites are created
 */
function default_jetpack_modules( $modules ) {
	$modules = array_diff( $modules, array( 'widget-visibility' ) );
	array_push( $modules, 'contact-form', 'shortcodes', 'custom-css', 'subscriptions' );

	return $modules;
}
add_filter( 'jetpack_get_default_modules', __NAMESPACE__ . '\default_jetpack_modules' );

/*
 * Enable Photon support for HTTPS URLs
 */
add_filter( 'jetpack_photon_reject_https', '__return_false' );

/**
 * Always automatically connect new sites to WordPress.com
 *
 * The UI for the auto-connect option is currently commented out in Jetpack. You can enable the setting manually,
 * but it will get overridden if you save the settings from the UI, because the form field is missing.
 *
 * @todo Remove this when the UI for the setting is launched.
 *
 * @param array $new_value
 * @param array $old_value
 *
 * @return array
 */
function auto_connect_new_sites( $new_value, $old_value ) {
	$new_value['auto-connect'] = 1;

	return $new_value;
}
add_filter( 'pre_update_site_option_jetpack-network-settings', __NAMESPACE__ . '\auto_connect_new_sites', 10, 2 );

/**
 * Sanitize parsed Custom CSS rules
 *
 * @import rules are stripped because they can introduce security vulnerabilities by embedding external
 * stylesheets that haven't been sanitized, and they also present a maintenance problem because they rely on
 * external resources which could go offline at any point.
 *
 * @charset rules are stripped because manipulating the charset can allow an attacker to introduce XSS
 * vulnerabilities by tricking the browser into interpreting the CSS as HTML.
 *
 * @param \safecss $safecss
 */
function sanitize_csstidy_parsed_rules( $safecss ) {
	if ( ! empty( $safecss->parser->import ) ) {
		update_option( 'custom_css_import_stripped', true );
	}

	$safecss->parser->import  = array();
	$safecss->parser->charset = array();
}
add_action( 'csstidy_optimize_postparse', __NAMESPACE__ . '\sanitize_csstidy_parsed_rules' );

/**
 * Notify the user that @import rules were stripped from their CSS
 */
function notify_import_rules_stripped() {
	global $current_screen;
	$relevant_screens = array( 'appearance_page_editcss', 'appearance_page_remote-css' );

	if ( ! is_a( $current_screen, 'WP_Screen' ) || ! in_array( $current_screen->id, $relevant_screens, true ) ) {
		return;
	}

	if ( ! get_option( 'custom_css_import_stripped' ) ) {
		return;
	}

	delete_option( 'custom_css_import_stripped' );

	?>

	<div class="notice notice-warning">
		<p>
			<?php printf(
				__( 'WARNING: <code>@import</code> rules were stripped for security reasons.
				Please use <a href="%s">the Fonts tool</a> to add web fonts, and merge other stylesheets directly into your custom CSS.',
				'wordcamporg' ),
              admin_url( 'themes.php?page=wc-fonts-options' )
            ); ?>
		</p>
	</div>

	<?php
}
add_action( 'admin_notices', __NAMESPACE__ . '\notify_import_rules_stripped' );

/**
 * Sanitize Custom CSS subvalues
 *
 * @param \safecss $safecss
 */
function sanitize_csstidy_subvalues( $safecss ) {
	$safecss->sub_value = trim( $safecss->sub_value );

	// Send any urls through our filter
	if ( preg_match( '!^\s*(?P<url_expression>url\s*(?P<opening_paren>\(|\\0028)(?P<parenthetical_content>.*)(?P<closing_paren>\)|\\0029))(.*)$!Dis', $safecss->sub_value, $matches ) ) {
		$safecss->sub_value = sanitize_urls_in_css_properties( $matches['parenthetical_content'], $safecss->property );

		// Only replace the url([...]) portion of the sub_value so we don't
		// lose things like trailing commas or !important declarations.
		if ( $safecss->sub_value ) {
			$safecss->sub_value = str_replace( $matches['url_expression'], $safecss->sub_value, $matches[0] );
		}
	}

	// Strip any expressions
	if ( preg_match( '!^\\s*expression!Dis', $safecss->sub_value ) ) {
		$safecss->sub_value = '';
	}
}
add_action( 'csstidy_optimize_subvalue', __NAMESPACE__ . '\sanitize_csstidy_subvalues' );

/**
 * Sanitize URLs used in CSS properties
 *
 * @param string $url
 * @param string $property
 *
 * @return string
 */
function sanitize_urls_in_css_properties( $url, $property ) {
	$allowed_properties = array( 'background', 'background-image', 'border-image', 'content', 'cursor', 'list-style', 'list-style-image' );
	$allowed_protocols  = array( 'http', 'https' );

	// Clean up the string
	$url = trim( $url, "' \" \r \n" );

	// Check against whitelist for properties allowed to have URL values
	if ( ! in_array( trim( $property ), $allowed_properties, true ) ) {
		// trim() is because multiple properties with the same name are stored with
		// additional trailing whitespace so they don't overwrite each other in the hash.
		return '';
	}

	$url = wp_kses_bad_protocol_once( $url, $allowed_protocols );

	if ( empty( $url ) ) {
		return '';
	}

	return "url('" . str_replace( "'", "\\'", $url ) . "')";
}

/**
 * Disable Jetpack's Holiday Snow on all WordCamp sites
 *
 * That option appears in Settings > General between December 1st and January 4th.
 * It is off by default.
 * This filter removes it completely.
 */
add_filter( 'jetpack_is_holiday_snow_season', '__return_false' );
