<?php

/*
 * Miscellaneous snippets that don't warrant their own file
 */


/*
 * Prevents 'index.php' from being prepended to permalink options.
 *
 * is_nginx() returns false on WordCamp.org because $_SERVER['SERVER_SOFTWARE'] is empty.
 */
add_filter( 'got_url_rewrite', '__return_true' );

/*
 * Include pages in the list of posts types that can have comments closed automatically
 */
function wcorg_close_comments_for_post_types( $post_types ) {
	$post_types[] = 'page';
	return $post_types;
}
add_filter( 'close_comments_for_post_types', 'wcorg_close_comments_for_post_types' );

/**
 * Force the `blog_public` option to be a specific value based on the site
 *
 * This ensures that normal camp sites are always indexed by search engines, and also
 * that they receive SSL certificates, because our Let's Encrypt script only installs
 * certificates for public sites.
 *
 * @param string $value
 *
 * @return string
 */
function wcorg_enforce_public_blog_option( $value ) {
	$private_sites = array(
		206,     // testing.wordcamp.org
	);

	if ( in_array( get_current_blog_id(), $private_sites, true ) ) {
		$value = '0';
	} else {
		$value = '1';
	}

	return $value;
}
add_filter( 'pre_update_option_blog_public', 'wcorg_enforce_public_blog_option' );

/*
 * We want to let organizers use shortcodes inside Text widgets
 */
add_filter( 'widget_text', 'do_shortcode' );

/**
 * Output a menu via a shortcode
 *
 * @param array $attributes
 *
 * @return string
 */
function wcorg_shortcode_menu( $attributes ) {
	$attributes = shortcode_atts(
		array(
			'menu'       => '',
			'menu_class' => 'menu',
			'depth'      => 1,
		),
		$attributes
	);

	$attributes['depth'] = absint( $attributes['depth'] );
	$attributes['echo']  = false;

	return wp_nav_menu( $attributes );
}
add_shortcode( 'menu', 'wcorg_shortcode_menu' );

/**
 * Disable certain network-activate plugins on specific sites.
 *
 * @param array $plugins
 *
 * @return array
 */
function wcorg_disable_network_activated_plugins_on_sites( $plugins ) {

	/*
	 * central.wordcamp.org, plan.wordcamp.org
     *
	 * These are plugins for individual WordCamp sites, so they aren't relevant for Central and Plan.
	 * They clutter the admin menu and slow down page loads.
	 */
	if ( in_array( get_current_blog_id(), array( BLOG_ID_CURRENT_SITE, 63 ) ) ) {
		unset( $plugins['camptix-extras/camptix-extras.php'] );
		unset( $plugins['camptix-network-tools/camptix-network-tools.php'] );
		unset( $plugins['tagregator/bootstrap.php'] );
		unset( $plugins['wc-canonical-years/wc-canonical-years.php'] );
		unset( $plugins['wordcamp-organizer-nags/wordcamp-organizer-nags.php'] );
	}

	/*
	 * plan.wordcamp.org
	 */
	if ( 63 === get_current_blog_id() ) {
		unset( $plugins['wordcamp-payments/bootstrap.php'] );
		unset( $plugins['wordcamp-payments-network/bootstrap.php'] );
	}

	return $plugins;
}
add_filter( 'site_option_active_sitewide_plugins', 'wcorg_disable_network_activated_plugins_on_sites' );

/*
 * Show Tagregator's log to network admins
 */
function wcorg_show_tagregator_log() {
	if ( current_user_can( 'manage_network' ) ) {
		add_filter( 'tggr_show_log', '__return_true' );
	}
}
add_action( 'init', 'wcorg_show_tagregator_log' );

/**
 * Prepend a unique string to contact form subjects.
 *
 * Otherwise some e-mail clients and management systems -- *cough* SupportPress *cough* -- will incorrectly group
 * separate messages into the same thread.
 *
 * It'd be better to have the key appended rather than prepended, but SupportPress won't always recognize the
 * subject as unique if we do that :|
 *
 * @param string $subject
 *
 * @return string
 */
function wcorg_grunion_unique_subject( $subject ) {
	return sprintf( '[%s] %s', wp_generate_password( 8, false ), $subject );
}
add_filter( 'contact_form_subject', 'wcorg_grunion_unique_subject' );

/**
 * Modify the space allocation on a per-size basis.
 *
 * @param int $size
 *
 * @return int
 */
function wcorg_modify_default_space_allotment( $size ) {
	switch ( get_current_blog_id() ) {
		case '364': // 2014.sf
			$size = 750;
			break;
	}

	return $size;
}
add_filter( 'get_space_allowed', 'wcorg_modify_default_space_allotment' );

/**
 * Redirects from /year/month/day/slug/ to /slug/ for new URL formats.
 */
function wcorg_subdomactories_redirect() {
	if ( ! is_404() )
		return;

	if ( get_option( 'permalink_structure' ) != '/%postname%/' )
		return;

	// russia.wordcamp.org/2014/2014/11/25/post-name/
	// russia.wordcamp.org/2014/11/25/post-name/
	// russia.wordcamp.org/2014/2014/25/post-name/
	// russia.wordcamp.org/2015-ru/...

	if ( ! preg_match( '#^/[0-9]{4}(?:-[^/]+)?/(?:[0-9]{4}/[0-9]{2}|[0-9]{2}|[0-9]{4})/[0-9]{2}/(.+)$#', $_SERVER['REQUEST_URI'], $matches ) )
		return;

	wp_safe_redirect( esc_url_raw( set_url_scheme( home_url( $matches[1] ) ) ) );
	die();
}
add_action( 'template_redirect', 'wcorg_subdomactories_redirect' );

/**
 * Add the post's slug to the body tag
 *
 * For CSS developers, this is better than relying on the post ID, because that often changes between their local
 * development environment and production, and manually importing/exporting is inconvenient.
 *
 * @param array $body_classes
 *
 * @return array
 */
function wcorg_content_slugs_to_body_tag( $body_classes ) {
	global $wp_query;
	$post = $wp_query->get_queried_object();

	if ( is_a( $post, 'WP_Post' ) ) {
		$body_classes[] = $post->post_type . '-slug-' . sanitize_html_class( $post->post_name, $post->ID );
	}

	return $body_classes;
}
add_filter( 'body_class', 'wcorg_content_slugs_to_body_tag' );

/*
 * Flush the rewrite rules on the current site.
 *
 * See WordCamp_CLI_Rewrite_Rules::flush() for an explanation.
 *
 * Requires authentication because flush_rewrite_rules() is expensive and could be used as a DoS vector.
 */
function wcorg_flush_rewrite_rules() {
	if ( isset( $_GET['nonce'] ) && wp_verify_nonce( $_GET['nonce'], 'flush-rewrite-rules-everywhere-' . get_current_blog_id() ) ) {
		flush_rewrite_rules();
		wp_send_json_success( 'Rewrite rules have been flushed.' );
	} else {
		wp_send_json_error( 'You are not authorized to flush the rewrite rules.' );
	}
}
add_action( 'wp_ajax_wcorg_flush_rewrite_rules_everywhere',        'wcorg_flush_rewrite_rules' ); // This isn't used by the wp-cli command, but is useful for manual testing
add_action( 'wp_ajax_nopriv_wcorg_flush_rewrite_rules_everywhere', 'wcorg_flush_rewrite_rules' );

/*
 * Load the `wordcamporg` text domain.
 *
 * `wordcamporg` is used by all the custom plugins and themes, so that translators only have to deal with a single
 * GlotPress project, and we only have to install/update a single mofile per locale.
 */
load_textdomain( 'wordcamporg', sprintf( '%s/languages/wordcamporg/wordcamporg-%s.mo', WP_CONTENT_DIR, get_locale() ) );

// WordCamp.org QBO Integration
add_filter( 'wordcamp_qbo_options', function( $options ) {
	if ( ! defined( 'WORDCAMP_QBO_CONSUMER_KEY' ) )
		return $options;

    // Secrets.
    $options['app_token'] = WORDCAMP_QBO_APP_TOKEN;
    $options['consumer_key'] = WORDCAMP_QBO_CONSUMER_KEY;
    $options['consumer_secret'] = WORDCAMP_QBO_CONSUMER_SECRET;
    $options['hmac_key'] = WORDCAMP_QBO_HMAC_KEY;

    // WordCamp Payments to QBO categories mapping.
    $options['categories_map'] = array(
        'after-party'     => array( 'value' => 72, 'name' => 'After Party' ),
        'audio-visual'    => array( 'value' => 79, 'name' => 'Audio-Visual' ),
        'food-beverages'  => array( 'value' => 64, 'name' => 'Food & Beverage-WordCamps' ),
        'office-supplies' => array( 'value' => 70, 'name' => 'Office Expense' ),
        'signage-badges'  => array( 'value' => 73, 'name' => 'Printing/Signage/Badges' ),
        'speaker-event'   => array( 'value' => 76, 'name' => 'Speaker Events' ),
        'swag'            => array( 'value' => 74, 'name' => 'Swag' ),
        'venue'           => array( 'value' => 78, 'name' => 'Venue Rental' ),
        'other'           => array( 'value' => 71, 'name' => 'Other Miscellaneous Expense' ),
    );

    return $options;
});

add_filter( 'wordcamp_qbo_client_options', function( $options ) {
	if ( ! defined( 'WORDCAMP_QBO_HMAC_KEY' ) )
		return $options;

    $options['hmac_key'] = WORDCAMP_QBO_HMAC_KEY;
    $options['api_base'] = 'https://central.wordcamp.org/wp-json/wordcamp-qbo/v1';

    return $options;
});

// Sponsorship payments (Stripe) credentials.
add_filter( 'wcorg_sponsor_payment_stripe', function( $options ) {
	$options['hmac_key'] = WORDCAMP_PAYMENT_STRIPE_HMAC;
	$options['publishable'] = WORDCAMP_PAYMENT_STRIPE_PUBLISHABLE;
	$options['secret'] = WORDCAMP_PAYMENT_STRIPE_SECRET;

	return $options;
});
