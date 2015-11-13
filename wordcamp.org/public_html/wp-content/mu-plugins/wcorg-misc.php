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
 * See WordCamp_Miscellaneous_Commands::flush_rewrite_rules_everywhere() for an explanation.
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
 * WP-CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * WordCamp.org Miscellaneous Commands
	 */
	class WordCamp_Miscellaneous_Commands extends WP_CLI_Command {
		/**
		 * Flush rewrite rules on all sites.
		 *
		 * Periodically they break for various reasons and need to be reset on all sites. If we
		 * just called flush_rewrite_rules() inside a switch_to_blog() loop then each site's
		 * plugins wouldn't be loaded and the rewrite rules wouldn't be correct.
		 *
		 * So instead, this issues an HTTP request to wcorg_flush_rewrite_rules() on each site so
		 * that flush_rewrite_rules() will run in the context of the loaded site.
		 *
		 * @subcommand flush-rewrite-rules-everywhere
		 */
		public function flush_rewrite_rules_everywhere() {
			$start_timestamp = microtime( true );
			$error           = '';
			$sites           = wp_get_sites( array( 'limit' => false ) );

			WP_CLI::line();

			foreach ( $sites as $site ) {
				$ajax_url    = sprintf( 'http://%s%swp-admin/admin-ajax.php', $site['domain'], $site['path'] );
				$display_url = $site['domain'] . rtrim( $site['path'], '/' );
				$nonce       = wp_create_nonce( 'flush-rewrite-rules-everywhere-' . $site['blog_id'] );

				$response = wp_remote_get( esc_url_raw( add_query_arg(
					array(
						'action' => 'wcorg_flush_rewrite_rules_everywhere',
						'nonce'  => $nonce,
					),
					$ajax_url
				) ) );

				if ( is_wp_error( $response ) ) {
					$success = false;
					$error   = $response->get_error_message();
				} else {
					$response = json_decode( wp_remote_retrieve_body( $response ) );

					if ( isset( $response->success ) && $response->success ) {
						$success = true;
					} else {
						$success = false;
						$error   = isset( $response->data ) ? $response->data : 'Unknown error';
					}
				}

				if ( $success ) {
					WP_CLI::line( sprintf( '%s: Flushed', $display_url ) );
				} else {
					WP_CLI::warning( sprintf( '%s: Failed with error: %s', $display_url, $error	) );
				}
			}
			$execution_time = microtime( true ) - $start_timestamp;

			WP_CLI::line();
			WP_CLI::line( sprintf(
				'Flushed all rewrite rules in %d minute(s) and %d second(s).',
				floor( $execution_time / 60 ),
				$execution_time % 60
			) );
		}
	}

	WP_CLI::add_command( 'wcorg-misc', 'WordCamp_Miscellaneous_Commands' );
}
