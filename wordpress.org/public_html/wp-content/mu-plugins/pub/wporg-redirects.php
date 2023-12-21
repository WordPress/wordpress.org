<?php

/*
 * WordPress.org/-specific redirects
 */
if ( 1 === get_current_blog_id() && is_multisite() && 'wordpress.org' === get_blog_details()->domain ) {
	add_action( 'template_redirect', function() {
		// WordPress.org/feed/* should redirect to WordPress.org/news/feed/*
		if ( is_feed() ) {
			wp_safe_redirect( '/news/feed/' . ( 'feed' !== get_query_var('feed') ? get_query_var('feed') : '' ), 301 );
			exit;

		// temp fix for /Blocks, rm later
		} elseif ( 0 === strpos( $_SERVER['REQUEST_URI'], '/Blocks' ) ) {
			wp_safe_redirect( '/blocks/', 301 );
			exit;

		// WordPress.org does not have a specific site search, only the global WordPress.org search
		} elseif ( ! empty( $_GET['s'] ) && false === strpos( $_SERVER['REQUEST_URI'], '/search/' ) ) {
			wp_safe_redirect( '/search/' . urlencode( wp_unslash( $_GET['s'] ) ) . '/', 301 );
			exit;

		} elseif ( is_404() ) {
			$path_redirects = [
				// The news blog is often thought to be at /blog
				'/blog' => '/news/',

				// The ideas forum was migrated to the Support Forums.
				'/extend/ideas' => '/support/forum/requests-and-feedback/',
				'/ideas'        => '/support/forum/requests-and-feedback/',

				// new Downloads pages https://meta.trac.wordpress.org/ticket/3673
				'/download/beta'            => '/download/beta-nightly/',
				'/download/nightly'         => '/download/beta-nightly/',
				'/download/release-archive' => '/download/releases/',
				'/download/legacy'          => '/download/',

				// Five for the Future site aliases
				'/5'                => '/five-for-the-future/',
				'/five'             => '/five-for-the-future/',
				'/5-for-the-future' => '/five-for-the-future/',
				'/5forthefuture'    => '/five-for-the-future/',
				'/fiveforthefuture' => '/five-for-the-future/',

				// Deprecated About / Testimonials page https://github.com/WordPress/wporg-main-2022/issues/196
				'/about/testimonials' => '/news/category/community/',
				// Deprecated About / Swag page https://github.com/WordPress/wporg-main-2022/issues/208
				'/about/swag'         => 'https://mercantile.wordpress.org/',

				// Hashtag alias for State of the Word
				'/sotw' => 'https://wordpress.org/state-of-the-word/',

				// Events
				'/events' => 'https://events.wordpress.org/',
				'/meet'   => 'https://events.wordpress.org/',

				// Data Liberation
				'/and' => '/data-liberation/',
			];

			foreach ( $path_redirects as $test => $redirect ) {
				if ( 0 === strpos( $_SERVER['REQUEST_URI'], $test ) ) {

					$code = 301;
					if ( is_array( $redirect ) ) {
						list( $code, $redirect ) = $redirect;
					}

					// override nocache_headers();
					header_remove( 'expires' );
					header_remove( 'cache-control' );

					wp_safe_redirect( $redirect, $code );
					exit;
				}
			}
		}
	}, 9 ); // Before redirect_canonical();
}

/**
 * Redirect some common urls to the proper location.
 */
add_action( 'template_redirect', function() {
	$host = $_SERVER['HTTP_HOST'];
	$path = $_SERVER['REQUEST_URI'];

	if ( ! is_404() ) {
		return;
	}

	$path_redirects = [
		// Singular => Plural for plugin/theme directories
		'/plugin/' => '/plugins/',
		'/theme/'  => '/themes/',

		// The plugin directory was available at /plugins-wp/ during a beta-test, and is still linked to.
		'/plugins-wp/' => '/plugins/',

		// Rosetta txt-download urls were changed to /download/.
		'/txt-download/' => '/downloads/',
	];

	if ( 'make.wordpress.org' === $host ) {
		// Slack invite url is /chat not /slack.
		$path_redirects['/slack'] = '/chat/';

		// Short URL for Gutenberg Phase 3 publicity
		$path_redirects['/phase-3'] = '/core/tag/phase-3/';
	}

	foreach ( $path_redirects as $test => $redirect ) {
		if ( 0 === strpos( $path, $test ) || 0 === strpos( $path . '/', $test ) ) {

			// Include any extra path components. (eg. /plugin/hello-dolly/)
			$path = substr( $path, strlen( $test ) );
			if ( $path ) {
				$redirect .= $path;
			}

			// override nocache_headers();
			header_remove( 'expires' );
			header_remove( 'cache-control' );

			wp_safe_redirect( $redirect, 301 );
			exit;
		}
	}

}, 9 );

/**
 * Redirect some invalid/malformed URL requests to their proper locations.
 */
add_action( 'template_redirect', function() {
	global $wp;

	if ( ! is_404() ) {
		return;
	}

	$name = false;
	foreach ( [ 'name', 'attachment', 'pagename' ] as $field ) {
		if ( isset( $wp->query_vars[ $field ] ) ) {
			// Get the raw unmodified query var from WP directly.
			$name = urldecode( $wp->query_vars[ $field ] );
		}
	}
	if ( ! $name ) {
		return;
	}

	$path = $_SERVER['REQUEST_URI'] ?? '/';
	// Remove the site prefix.
	$path = preg_replace( '!^' . preg_quote( wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '!' ) . '!', '/', $path );

	$new_path = $path;

	// Remove any common URL paths.
	$new_path = preg_replace( '!^/?(index|contact(-us)?)(\.(html?|php))?!i', '', $new_path );

	// Remove any `<a>` attributes from the URL.
	$new_path = preg_replace( '!(target|rel|href)=.*$!i', '', $new_path );

	// Remove any trailing punctuation.
	$new_path = preg_replace( '!([ +\'"]|(?:%20))+$!', '', $new_path );

	if ( $path === $new_path ) {
		return;
	}

	// Determine URL, save a redirect and check the canonical too.
	$url = home_url( $new_path ?: '/' );
	if ( $canonical_url = redirect_canonical( $url, false ) ) {
		$url = $canonical_url;
	}

	wp_safe_redirect( $url, 301, 'W.org Redirects Malformed URLs' );
	die();

}, 20 ); // After canonical.

/**
 * Handle the domain-based redirects
 *
 * Called from sunrise.php on ms_site_not_found and ms_network_not_found actions.
 */
function wporg_redirect_site_not_found() {
	$location    = '';
	$status_code = 301;
	$host        = strtolower( $_SERVER['HTTP_HOST'] );

	switch ( $host ) {
		// :earth_asia::earth_africa::earth_americas:.wordpress.org
		case 'xn--tg8hcb.wordpress.org':
			$location = 'https://emoji.wordpress.org/';
			break;

		// Singular => Plural
		case 'profile.wordpress.org':
			$location = 'https://profiles.wordpress.org' . $_SERVER['REQUEST_URI'];
			break;

		// WordPress.org => WordPress.net
		case 'wp15.wordpress.org':
		case 'wp20.wordpress.org':
		case 'jobs.wordpress.org':
		// Default Theme Demo sites are on WordPress.net
		case '2017.wordpress.org':
		case '2019.wordpress.org':
		case '2020.wordpress.org':
		case '2021.wordpress.org':
		case '2022.wordpress.org':
		case '2023.wordpress.org':
		case '2024.wordpress.org':
		case '2025.wordpress.org':
			$location = 'https://' . explode( '.', $host )[0] . '.wordpress.net/';
			break;

		case 'slack.wordpress.org':
		case 'chat.wordpress.org':
			$location = 'https://make.wordpress.org/chat/';
			break;

		case 'community.wordpress.org':
			$location    = 'https://make.wordpress.org/chat/matrix/';
			$status_code = 302;
			break;

		// Plural => Singular
		case 'developers.wordpress.org':
			$location = 'https://developer.wordpress.org/';
			break;

		// This should absolutely never happen, exit without a redirect.
		case 'wordpress.org':
			status_header( 503 );
			die( 'WordPress.org is currently unavailable.' );
			break;

		// Default location for a not-found site or network is the main WordPress.org homepage.
		default:
			$location = 'https://wordpress.org/';
			break;
	}

	if ( ! headers_sent() ) {
		header( 'Location: ' . $location, true, $status_code );
	} else {
		// Headers should not have been sent at this point in time.
		// On some pages, such as wp-cron.php the request has been terminated prior to WordPress loading, and so headers were "sent".
		echo "<a href='$location'>$location</a>";
	}
	exit;
}

/**
 * Redirect w.org/contributor-training/ to it's new home on Learn.
 */
add_action( 'template_redirect', function() {
	$path = strtolower( $_SERVER['REQUEST_URI'] ?? '/' );
	if ( 'wordpress.org' !== $_SERVER['HTTP_HOST'] || ! str_starts_with( $path, '/contributor-training' ) ) {
		return;
	}

	$redirects = [
		'/contributor-training/course/how-decisions-are-made-in-the-wordpress-project' => 'https://learn.wordpress.org/course/how-decisions-are-made-in-the-wordpress-project/',
		'/contributor-training/course/writing-in-the-wordpress-voice'                  => 'https://learn.wordpress.org/course/writing-in-the-wordpress-voice/',
		'/contributor-training/course/basic-principles-of-conflict-resolution'         => 'https://learn.wordpress.org/course/basic-principles-of-conflict-resolution/',
		'/contributor-training/course/meeting-etiquette'                               => 'https://learn.wordpress.org/course/community-meeting-etiquette/',
		'/contributor-training/course/wordpress-meetup-organizer-training'             => 'https://learn.wordpress.org/course/wordpress-meetup-organizer-training/',
		'/contributor-training/course/open-source-basics-and-wordpress/'               => 'https://learn.wordpress.org/course/open-source-basics-and-wordpress/',
		'/contributor-training/course/wordpress-community-deputy-training/'            => 'https://learn.wordpress.org/course/wordpress-community-deputy-training/',
		'/contributor-training/course/wordcamp-organizer-training/'                    => 'https://learn.wordpress.org/course/wordcamp-organizer-training/',
		'/contributor-training/course/wordcamp-mentor-training/'                       => 'https://learn.wordpress.org/course/wordcamp-mentor-training/',
	];

	foreach ( $redirects as $match => $redirect ) {
		if ( str_starts_with( $path, $match ) ) {
			wp_safe_redirect( $redirect, 301, 'Contributor Training to Learn' );
			exit;
		}
	}

	// If no specific course match, search for make-specific courses.
	wp_safe_redirect( 'https://learn.wordpress.org/course-category/contributing-to-wordpress/', 301, 'Contributor Training to Learn' );
	exit;
} );

/**
 * Redirect developer.wp.org/playground/ to github documentation.
 */
add_action( 'template_redirect', function() {
	$path = strtolower( $_SERVER['REQUEST_URI'] ?? '/' );
	if ( 'developer.wordpress.org' !== $_SERVER['HTTP_HOST'] || ! str_starts_with( $path, '/playground' ) ) {
		return;
	}

	wp_redirect( 'https://wordpress.github.io/wordpress-playground/', 301 );
	exit;
} );

// Add wp.org redirect from developer.wp.org see: https://github.com/WordPress/wporg-developer/issues/452
add_action( 'parse_request', function() {
	$path = strtolower( $_SERVER['REQUEST_URI'] ?? '/' );
	if ( 'developer.wordpress.org' !== $_SERVER['HTTP_HOST'] || '/themes/getting-started/wordpress-licensing-the-gpl/' !== $path ) {
		return;
	}

	wp_safe_redirect( '	https://wordpress.org/about/license/', 301, 'wporg dev redirect'  );
	exit;
} );

